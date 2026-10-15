"""
BeeGuard - USB Webcam with MJPEG Live Stream + Bee/Insect Detection

The script runs TWO things at once:
  1. MJPEG video server  → http://localhost:5050/stream  (true live, zero delay)
  2. Bee/insect detector → sends counts to BeeGuard dashboard every 2 s

The dashboard <img> points directly to the MJPEG stream — no file saving,
no polling, no delay. Just like a security camera feed.

Requirements:
    pip install ultralytics huggingface_hub opencv-python requests numpy

Usage:
    python camera_capture.py
"""

import cv2
import numpy as np
import requests
import time
import sys
import json
import threading
import argparse
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

# ── CLI arguments (set automatically by camera_control.php) ───
_ap = argparse.ArgumentParser()
_ap.add_argument('--hive-id',      type=int, default=1)
_ap.add_argument('--camera-index', type=int, default=1)
_args = _ap.parse_args()

# ── Configuration ─────────────────────────────────────────────
SERVER_URL       = "http://localhost/beemonitoring/api/receive_camera.php"
API_KEY          = "BEEGUARD_SECRET_2026"
HIVE_ID          = _args.hive_id
CAMERA_INDEX     = _args.camera_index  # passed by camera_control.php per hive
STREAM_PORT      = 5050        # MJPEG stream port → http://localhost:5050/stream
STATS_INTERVAL   = 3.0         # seconds between detection stats sent to PHP
FRAME_WIDTH      = 960         # wider frame = more bee detail for YOLO
FRAME_HEIGHT     = 540         # 16:9 ratio, matches most USB webcams natively
JPEG_QUALITY     = 75          # smaller JPEG = faster MJPEG stream
DETECT_EVERY_N   = 4           # YOLO runs at ~7 fps — good balance CPU vs accuracy

YOLO_CONF        = 0.90        # high confidence — minimal false positives, catches real insects
MAX_INSECT_AREA  = 0.05        # reject any bee/insect box > 5% of frame (too large to be an insect)
YOLO_IOU         = 0.40
CUSTOM_MODEL     = 'models/bee_insect.pt'
BEE_MODEL_ID     = 'hf-hub:keremberke/yolov8n-bee-detection'
FALLBACK_MODEL   = 'yolov8n.pt'

# Classes that the custom model knows about
# bee = honeybee (nonpollenbee + pollenbee merged), insect = non-bee insect (wasp, fly, etc.)
CUSTOM_CLASSES   = {"bee", "insect"}
BEE_CLASSES      = {"bee"}       # counted as bees
INSECT_CLASSES   = {"insect"}    # triggers alert when detected at hive entrance
# ──────────────────────────────────────────────────────────────

# ── Shared frame buffer (written by capture thread, read by MJPEG server)
_frame_lock  = threading.Lock()
_frame_bytes = None   # latest JPEG bytes
_meta        = {'bee_count': 0, 'insect_count': 0,
                'timestamp': '', 'online': False}


def set_frame(frame_bgr, dets):
    global _frame_bytes, _meta
    ok, buf = cv2.imencode('.jpg', frame_bgr,
                           [cv2.IMWRITE_JPEG_QUALITY, JPEG_QUALITY])
    if ok:
        with _frame_lock:
            _frame_bytes = buf.tobytes()
            _meta = {
                'bee_count':    sum(1 for d in dets if d['label'] in BEE_CLASSES),
                'insect_count': sum(1 for d in dets if d['label'] in INSECT_CLASSES),
                'timestamp':    time.strftime('%Y-%m-%d %H:%M:%S'),
                'online':       True,
            }


def get_frame():
    with _frame_lock:
        return _frame_bytes


def get_meta():
    with _frame_lock:
        return dict(_meta)


# ─────────────────────────────────────────────────────────────
#  MJPEG HTTP SERVER
# ─────────────────────────────────────────────────────────────
class StreamHandler(BaseHTTPRequestHandler):

    def do_GET(self):
        path = self.path.split('?')[0]   # strip query string (?t=... cache-busters)
        if path == '/stream':
            self._stream()
        elif path in ('/ping', '/meta'):
            self._json(get_meta())
        else:
            self.send_response(404)
            self.end_headers()

    def _stream(self):
        self.send_response(200)
        self.send_header('Content-Type',
                         'multipart/x-mixed-replace; boundary=BeeGuardFrame')
        self.send_header('Cache-Control', 'no-cache, no-store, must-revalidate')
        self.send_header('Access-Control-Allow-Origin', '*')
        self.end_headers()
        try:
            while True:
                fb = get_frame()
                if fb:
                    self.wfile.write(
                        b'--BeeGuardFrame\r\n'
                        b'Content-Type: image/jpeg\r\n\r\n' +
                        fb + b'\r\n'
                    )
                time.sleep(0.04)   # ~25 fps cap
        except (BrokenPipeError, ConnectionResetError):
            pass

    def _json(self, data):
        body = json.dumps(data).encode()
        self.send_response(200)
        self.send_header('Content-Type',  'application/json')
        self.send_header('Content-Length', str(len(body)))
        self.send_header('Cache-Control', 'no-cache')
        self.send_header('Access-Control-Allow-Origin', '*')
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, *_):
        pass   # suppress HTTP log noise


def start_stream_server():
    server = ThreadingHTTPServer(('0.0.0.0', STREAM_PORT), StreamHandler)
    t = threading.Thread(target=server.serve_forever, daemon=True)
    t.start()
    print(f"[STREAM] MJPEG server running → http://localhost:{STREAM_PORT}/stream")


# ─────────────────────────────────────────────────────────────
#  LOAD MODELS
# ─────────────────────────────────────────────────────────────
bee_model     = None
general_model = None
MODEL_MODE    = 'motion_only'

try:
    from ultralytics import YOLO
    import os

    # ── Bee / insect model ─────────────────────────────────────
    custom_path = os.path.join(os.path.dirname(__file__), CUSTOM_MODEL)
    if os.path.exists(custom_path):
        print(f"[MODEL] ✅ Custom bee model: {custom_path}")
        bee_model  = YOLO(custom_path)
        MODEL_MODE = 'custom'
        print(f"[MODEL]    Classes: {list(bee_model.names.values())}")
    else:
        try:
            print("[MODEL] Downloading bee model (HuggingFace ~7 MB)...")
            bee_model  = YOLO(BEE_MODEL_ID)
            MODEL_MODE = 'bee_specific'
            print(f"[MODEL] ✅ Bee model ready")
        except Exception as e:
            print(f"[MODEL] Bee model failed ({e}), using YOLOv8n...")
            general_model = YOLO(FALLBACK_MODEL)
            MODEL_MODE    = 'general'

except ImportError:
    print("[MODEL] ultralytics not found — motion+color fallback active")

bg_sub = cv2.createBackgroundSubtractorMOG2(
    history=300, varThreshold=55, detectShadows=False)


# ─────────────────────────────────────────────────────────────
#  DETECTION HELPERS
# ─────────────────────────────────────────────────────────────
def find_moving_regions(frame):
    fg     = bg_sub.apply(frame)
    kernel = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (5, 5))
    fg     = cv2.morphologyEx(fg, cv2.MORPH_OPEN, kernel)
    fg     = cv2.dilate(fg, kernel, iterations=2)
    contours, _ = cv2.findContours(fg, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    out = []
    for cnt in contours:
        a = cv2.contourArea(cnt)
        if 600 < a < 30000:
            x, y, w, h = cv2.boundingRect(cnt)
            if 0.15 < w/max(h,1) < 6.0:
                out.append((x, y, x+w, y+h))
    return out


def boxes_overlap(motion_box, yolo_box):
    """Return True if the motion region is already covered by a YOLO detection.

    Uses two checks (either is enough):
      1. Center of motion box is inside the YOLO box (most reliable)
      2. IoU >= 0.10  (catches partial overlaps)
    """
    mx1, my1, mx2, my2 = motion_box
    yx1, yy1, yx2, yy2 = yolo_box

    # Check 1 — center containment
    cx = (mx1 + mx2) / 2
    cy = (my1 + my2) / 2
    if yx1 <= cx <= yx2 and yy1 <= cy <= yy2:
        return True

    # Check 2 — IoU >= 0.10
    ix1 = max(mx1, yx1); iy1 = max(my1, yy1)
    ix2 = min(mx2, yx2); iy2 = min(my2, yy2)
    inter = max(0, ix2 - ix1) * max(0, iy2 - iy1)
    if not inter:
        return False
    union = (mx2-mx1)*(my2-my1) + (yx2-yx1)*(yy2-yy1) - inter
    return inter / union >= 0.10


def color_is_bee(frame, x1, y1, x2, y2):
    roi = frame[y1:y2, x1:x2]
    if not roi.size: return False
    hsv = cv2.cvtColor(roi, cv2.COLOR_BGR2HSV)
    m   = cv2.bitwise_or(
            cv2.inRange(hsv, np.array([12,80,80]),  np.array([32,255,255])),
            cv2.inRange(hsv, np.array([8,100,50]),  np.array([18,255,200])))
    return np.count_nonzero(m) / (roi.shape[0]*roi.shape[1]+1) >= 0.12


def overlap_ratio(a, b):
    """Fraction of box a that is covered by box b."""
    ix1, iy1 = max(a[0], b[0]), max(a[1], b[1])
    ix2, iy2 = min(a[2], b[2]), min(a[3], b[3])
    if ix2 <= ix1 or iy2 <= iy1:
        return 0.0
    inter = (ix2 - ix1) * (iy2 - iy1)
    area_a = max(1, (a[2]-a[0]) * (a[3]-a[1]))
    return inter / area_a


def detect(frame):
    h, w = frame.shape[:2]; fa = h * w
    dets = []

    # ── Bee / insect detection only ────────────────────────────
    if MODEL_MODE in ('custom', 'bee_specific'):
        bee_boxes = []
        for r in bee_model(frame, verbose=False, conf=YOLO_CONF, iou=YOLO_IOU):
            for box in r.boxes:
                x1,y1,x2,y2 = map(int, box.xyxy[0].tolist())
                box_area = (x2-x1) * (y2-y1)
                if box_area / fa > MAX_INSECT_AREA:
                    continue  # too large to be a real insect
                cls  = bee_model.names[int(box.cls[0])].lower()
                conf = round(float(box.conf[0]), 2)
                bee_boxes.append((x1,y1,x2,y2))
                label = cls if cls in CUSTOM_CLASSES else 'insect'
                dets.append({'x1':x1,'y1':y1,'x2':x2,'y2':y2,
                             'label':label, 'conf':conf, 'source':'bee_model'})

        # Motion fallback for missed insects
        for (mx1,my1,mx2,my2) in find_moving_regions(frame):
            if (mx2-mx1)*(my2-my1)/fa > 0.20: continue
            if any(boxes_overlap((mx1,my1,mx2,my2), b) for b in bee_boxes): continue
            label = 'bee' if color_is_bee(frame, mx1, my1, mx2, my2) else 'insect'
            dets.append({'x1':mx1,'y1':my1,'x2':mx2,'y2':my2,
                         'label':label, 'conf':0.50, 'source':'motion'})

    elif MODEL_MODE == 'general':
        for r in general_model(frame, verbose=False, conf=YOLO_CONF, iou=YOLO_IOU):
            for box in r.boxes:
                x1,y1,x2,y2 = map(int, box.xyxy[0].tolist())
                if (x2-x1)*(y2-y1)/fa > 0.18: continue
                label = 'bee' if color_is_bee(frame,x1,y1,x2,y2) else 'insect'
                dets.append({'x1':x1,'y1':y1,'x2':x2,'y2':y2,
                             'label':label,'conf':round(float(box.conf[0]),2),'source':'general'})
    else:
        for (x1,y1,x2,y2) in find_moving_regions(frame):
            label = 'bee' if color_is_bee(frame,x1,y1,x2,y2) else 'insect'
            dets.append({'x1':x1,'y1':y1,'x2':x2,'y2':y2,'label':label,'conf':0.60,'source':'motion'})

    return dets


# ─────────────────────────────────────────────────────────────
#  ANNOTATE FRAME
# ─────────────────────────────────────────────────────────────
# BGR color per label
LABEL_COLOR = {
    'bee':    ( 34, 197,  94),   # green  — honeybee
    'insect': (  0,  80, 220),   # red    — non-bee insect (alert!)
}

def annotate(frame, dets):
    for d in dets:
        label  = d['label']
        source = d.get('source', 'bee_model')
        bgr    = LABEL_COLOR.get(label, (60, 60, 200))
        x1,y1,x2,y2 = d['x1'],d['y1'],d['x2'],d['y2']

        thickness = 3 if label == 'person' else 2
        cv2.rectangle(frame,(x1,y1),(x2,y2),bgr,thickness)
        txt = f"{label.capitalize()} {int(d['conf']*100)}%"
        (tw,th),_ = cv2.getTextSize(txt,cv2.FONT_HERSHEY_SIMPLEX,0.46,1)
        cv2.rectangle(frame,(x1,y1-th-8),(x1+tw+6,y1),bgr,-1)
        cv2.putText(frame,txt,(x1+3,y1-4),
                    cv2.FONT_HERSHEY_SIMPLEX,0.46,(255,255,255),1,cv2.LINE_AA)

    # HUD bar
    ov = frame.copy()
    cv2.rectangle(ov,(0,0),(frame.shape[1],28),(15,23,42),-1)
    cv2.addWeighted(ov,0.72,frame,0.28,0,frame)
    mode_lbl = {'custom':'BeeGuard','bee_specific':'BeeYOLO',
                'general':'YOLOv8','motion_only':'Motion'}[MODEL_MODE]
    ts = time.strftime('%H:%M:%S')
    cv2.putText(frame,f"BeeGuard [{mode_lbl}] | {ts}",
                (6,20),cv2.FONT_HERSHEY_SIMPLEX,0.46,(255,255,255),1,cv2.LINE_AA)

    bee_n = sum(1 for d in dets if d['label'] == 'bee')
    ins_n = sum(1 for d in dets if d['label'] in CUSTOM_CLASSES and d['label'] != 'bee')

    parts = [f"Bees:{bee_n}"]
    if ins_n: parts.append(f"Insects:{ins_n}")
    summary = "  ".join(parts)

    (sw,_),_ = cv2.getTextSize(summary,cv2.FONT_HERSHEY_SIMPLEX,0.46,1)
    col = (80,80,250) if ins_n else (80,220,100)
    cv2.putText(frame,summary,(frame.shape[1]-sw-8,20),
                cv2.FONT_HERSHEY_SIMPLEX,0.46,col,1,cv2.LINE_AA)
    return frame


# ─────────────────────────────────────────────────────────────
#  SEND DETECTION STATS TO PHP (no image — image goes via MJPEG)
# ─────────────────────────────────────────────────────────────
def send_stats(dets):
    bee_n = sum(1 for d in dets if d['label'] == 'bee')
    ins_n = sum(1 for d in dets if d['label'] in CUSTOM_CLASSES and d['label'] != 'bee')
    try:
        requests.post(SERVER_URL,
            data={'api_key': API_KEY, 'hive_id': HIVE_ID,
                  'detections':  json.dumps(dets),
                  'bee_count':   bee_n,
                  'insect_count':ins_n,
                  'stream_mode': '1'},
            timeout=3)
    except Exception:
        pass


# ─────────────────────────────────────────────────────────────
#  CAMERA HELPERS
# ─────────────────────────────────────────────────────────────
def open_camera(index):
    # Try backends in order: MSMF → DSHOW → default
    # MSMF works better with most USB webcams on Windows
    for backend in (cv2.CAP_MSMF, cv2.CAP_DSHOW, cv2.CAP_ANY):
        cap = cv2.VideoCapture(index, backend)
        if not cap.isOpened():
            continue
        # Verify we actually get a real frame (not black)
        ok = False
        for _ in range(5):
            ret, frame = cap.read()
            if ret and frame is not None and frame.mean() > 1.0:
                ok = True
                break
        if ok:
            cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
            cap.set(cv2.CAP_PROP_FPS, 30)
            print(f"[CAM] Backend {backend} works ✅")
            return cap
        cap.release()
    # Last resort — open without any resolution or backend constraint
    cap = cv2.VideoCapture(index)
    return cap


def scan_cameras():
    print("[CAM] Scanning cameras...")
    found = []
    for i in range(7):
        for backend in (cv2.CAP_MSMF, cv2.CAP_DSHOW, cv2.CAP_ANY):
            cap = cv2.VideoCapture(i, backend)
            if not cap.isOpened():
                cap.release()
                continue
            # Read a few frames to confirm it produces real (non-black) output
            real = False
            for _ in range(3):
                ret, frame = cap.read()
                if ret and frame is not None and frame.mean() > 1.0:
                    real = True
                    break
            cap.release()
            if real:
                found.append(i)
                print(f"      [{i}] ✅ found (backend {backend})")
                break  # no need to try other backends for this index
    return found


def pick_usb(found):
    if not found: return None
    if len(found) == 1:
        return found[0]
    # Prefer highest index — Windows assigns higher indices to externally-plugged USB cameras
    usb = sorted([i for i in found if i > 0], reverse=True)
    chosen = usb[0] if usb else found[-1]
    print(f"[CAM] Available={found} | Selected USB webcam=index {chosen} ✅")
    return chosen


# ─────────────────────────────────────────────────────────────
#  OFFLINE PLACEHOLDER FRAME
# ─────────────────────────────────────────────────────────────
def make_offline_frame():
    """Dark frame with 'Camera Disconnected' text pushed to the MJPEG stream."""
    img = np.zeros((FRAME_HEIGHT, FRAME_WIDTH, 3), dtype=np.uint8)
    img[:] = (30, 30, 30)
    cx, cy = FRAME_WIDTH // 2, FRAME_HEIGHT // 2
    # Draw camera icon outline
    cv2.rectangle(img, (cx-60, cy-40), (cx+60, cy+20), (80, 80, 80), 2)
    cv2.circle(img, (cx, cy-10), 18, (80, 80, 80), 2)
    # Text lines
    for text, y_off, scale, color in [
        ("Camera Disconnected", -70, 0.75, (80, 80, 220)),
        ("Plug in the USB camera",  -30, 0.55, (160, 160, 160)),
        ("Reconnecting...",          10, 0.50, (100, 180, 100)),
        (time.strftime('%H:%M:%S'), 50, 0.45, (120, 120, 120)),
    ]:
        (tw, th), _ = cv2.getTextSize(text, cv2.FONT_HERSHEY_SIMPLEX, scale, 1)
        cv2.putText(img, text, (cx - tw // 2, cy + y_off + th // 2),
                    cv2.FONT_HERSHEY_SIMPLEX, scale, color, 1, cv2.LINE_AA)
    return img


# ─────────────────────────────────────────────────────────────
#  MAIN
# ─────────────────────────────────────────────────────────────
# How many consecutive read failures before we assume the camera is gone
_FAIL_LIMIT      = 40   # ~2 s at 0.05 s/frame
_RECONNECT_WAIT  = 4.0  # seconds between reconnect attempts


def main():
    print("=" * 60)
    print("  BeeGuard — MJPEG Live Stream + Bee/Insect Detection")
    print(f"  Stream  : http://localhost:{STREAM_PORT}/stream  (zero delay)")
    print(f"  Model   : {MODEL_MODE}")
    print("  GREEN = Bee   |   RED = Other insect")
    print("  Ctrl+C to stop")
    print("=" * 60)

    # Start MJPEG server in background thread
    start_stream_server()

    def find_camera():
        idx = CAMERA_INDEX
        if idx is None:
            idx = pick_usb(scan_cameras())
        return idx

    def try_open(idx):
        if idx is None:
            return None
        cap = open_camera(idx)
        if cap and cap.isOpened():
            print(f"[CAM] ✅ Camera {idx} opened")
            return cap
        if cap:
            cap.release()
        return None

    last_stats  = 0
    frame_count = 0
    last_dets   = []
    fail_count  = 0
    cap         = None

    # Initial connect
    cap = try_open(find_camera())
    if cap is None:
        print("[CAM] No camera found at startup — will keep retrying...")

    print("[OK] Open hives.php → click a hive → live feed appears instantly\n")

    try:
        while True:

            # ── Camera is offline → push placeholder and retry ──────────
            if cap is None or not cap.isOpened():
                offline = make_offline_frame()
                ok, buf = cv2.imencode('.jpg', offline,
                                       [cv2.IMWRITE_JPEG_QUALITY, JPEG_QUALITY])
                if ok:
                    with _frame_lock:
                        global _frame_bytes, _meta
                        _frame_bytes = buf.tobytes()
                        _meta = {'bee_count': 0, 'insect_count': 0,
                                 'timestamp': time.strftime('%Y-%m-%d %H:%M:%S'),
                                 'online': False}
                time.sleep(_RECONNECT_WAIT)
                print(f"[CAM] Camera offline — scanning for USB webcam...")
                new_cap = try_open(find_camera())
                if new_cap:
                    cap        = new_cap
                    fail_count = 0
                    last_dets  = []
                    frame_count = 0
                    bg_sub     # keep existing bg_sub; it will warm up again naturally
                continue

            # ── Normal frame read ────────────────────────────────────────
            ret, frame = cap.read()

            if not ret or frame is None:
                fail_count += 1
                time.sleep(0.05)
                if fail_count >= _FAIL_LIMIT:
                    print(f"[CAM] ⚠️  {fail_count} consecutive read failures — camera unplugged?")
                    cap.release()
                    cap        = None
                    fail_count = 0
                    last_dets  = []
                continue

            fail_count  = 0
            frame_count += 1

            # Run heavy YOLO detection only every N frames to save CPU
            if frame_count % DETECT_EVERY_N == 0:
                last_dets = detect(frame)

            dets  = last_dets
            frame = annotate(frame, dets)

            # Push to MJPEG buffer (instant, no disk write)
            set_frame(frame, dets)

            # Send detection stats to PHP every STATS_INTERVAL seconds
            now = time.time()
            if now - last_stats >= STATS_INTERVAL:
                last_stats = now
                threading.Thread(target=send_stats, args=(dets,), daemon=True).start()
                bee_n  = sum(1 for d in dets if d['label'] == 'bee')
                ins_n  = sum(1 for d in dets if d['label'] in CUSTOM_CLASSES and d['label'] != 'bee')
                status = "⚠️ INSECT" if ins_n else "✅"
                print(f"[{time.strftime('%H:%M:%S')}] Bees:{bee_n}  Insects:{ins_n}  {status}")

    except KeyboardInterrupt:
        print("\n[STOP] Bye!")
    finally:
        if cap:
            cap.release()


if __name__ == '__main__':
    main()

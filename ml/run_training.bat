@echo off
set PYTHONIOENCODING=utf-8
echo [Setup] Installing Python dependencies...
python -m pip install --no-cache-dir --prefer-binary -r "C:\xampp\htdocs\beemonitoring\ml\requirements.txt" >> "C:\xampp\htdocs\beemonitoring\ml\training.log" 2>&1
python "C:\xampp\htdocs\beemonitoring\ml\train_model.py" >> "C:\xampp\htdocs\beemonitoring\ml\training.log" 2>&1

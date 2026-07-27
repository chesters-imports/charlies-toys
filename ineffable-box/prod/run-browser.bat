@echo off
cd /d "%~dp0box_sys"
start "" "http://127.0.0.1:43120/"
python server.py

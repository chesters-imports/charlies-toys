# KDE Notes & Chords

**SKU:** `CO.KDE-001-INSTR`  
**Maker:** **KDE Fugue Inc.** · `CO.KDE`  
(looping pocket machines + amnesia-detective line; CO.FIN merged into CO.KDE)

**Also known as:** Instrumental Stories · KDE Notes & Chords (old FUGUE games door)

**One horizontal strip of 15 keys** (not a 5×3 slot cabinet).  
Position on the strip = note; matches dump a **chord** and **narrative fragments**.  
Self-contained. No myPI getTool.

**Mausoleum originals:**  
`Builds/my-pocket-internet/.../pageInstrumentalStories.php`  
`m/doors/www/games/KDENotesAndChords.php`

## Run (Deck Host · 800×600 pocket)

```bat
cd C:\ALICE_BOX\charlies-toys\kde-notes-chords\prod
run-kde.bat
```

Override size: `set KDE_WIDTH=800` / `set KDE_HEIGHT=600` before launch.

## Ship (itch · one folder)

Deck Host serves the ROM **in-process**. Offline: Tone.js + latin faces live in `prod/kde_sys/` (no CDN).

```bat
cd C:\ALICE_BOX\charlies-toys\kde-notes-chords\tools
python pack-itch.py
```

Writes `ship/KDE-Notes-and-Chords/`. With PyInstaller (`pip install pyinstaller`) that folder is the itch zip — double-click `KDE Notes and Chords.exe`. Without it, `run-dev.bat` still uses your local Python.

Windows 10/11 · Edge WebView2 (already on most machines).

## Same house

- **CO.KDE-002-KD1** · My PI: Kitten Detective Chapter I (catalog)  

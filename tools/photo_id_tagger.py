"""
Photo ID Tagger
===============
Reads a CSV exported from the Photo ID web app and writes "Person Shown"
(Iptc4xmpExt:PersonInImage) XMP metadata to matching image files using ExifTool.

Requirements:
  - Python 3.6+
  - ExifTool (https://exiftool.org) — place exiftool.exe in the same folder
    as this script, or add it to your PATH.

Usage:
  Run this script and use the GUI to select the CSV file and image folder.
"""

import csv
import os
import shutil
import subprocess
import sys
import threading
import tkinter as tk
from tkinter import filedialog, scrolledtext, ttk


# ---------------------------------------------------------------------------
# Filename matching
# ---------------------------------------------------------------------------

def normalize(filename):
    """
    Normalize a filename the same way the upload sanitizer does:
    replace any character that isn't alphanumeric, dot, or dash with underscore,
    then lowercase for case-insensitive comparison.
    e.g. "My Photo - 001.jpg" -> "my_photo___001.jpg"
    """
    import re
    return re.sub(r'[^a-zA-Z0-9.\-]', '_', filename).lower()


def build_folder_index(folder):
    """
    Return a dict mapping normalized filename -> actual filename
    for every file in the folder.
    """
    index = {}
    for f in os.listdir(folder):
        if os.path.isfile(os.path.join(folder, f)):
            index[normalize(f)] = f
    return index


def find_image(folder, csv_filename, index):
    """
    Find the actual file in folder matching csv_filename.
    1. Exact match
    2. Normalized match (handles spaces -> underscores, etc.)
    Returns full path or None.
    """
    exact = os.path.join(folder, csv_filename)
    if os.path.isfile(exact):
        return exact
    actual = index.get(normalize(csv_filename))
    if actual:
        return os.path.join(folder, actual)
    return None


# ---------------------------------------------------------------------------
# ExifTool helpers
# ---------------------------------------------------------------------------

def find_exiftool():
    """Return path to exiftool executable, or None if not found."""
    script_dir = os.path.dirname(os.path.abspath(sys.argv[0]))

    # Check script directory directly
    candidate = os.path.join(script_dir, "exiftool.exe")
    if os.path.isfile(candidate):
        return candidate

    # Check any subfolder whose name contains "exiftool"
    try:
        for entry in os.listdir(script_dir):
            if "exiftool" in entry.lower():
                subfolder = os.path.join(script_dir, entry)
                if os.path.isdir(subfolder):
                    for f in os.listdir(subfolder):
                        if f.lower().endswith(".exe") and "exiftool" in f.lower():
                            return os.path.join(subfolder, f)
    except OSError:
        pass

    return shutil.which("exiftool") or shutil.which("exiftool.exe")


def run_exiftool(exiftool_path, image_path, names):
    """
    Write PersonInImage tags to an image file.
    Returns (returncode, stdout, stderr).
    """
    tag = "XMP-iptcExt:PersonInImage"
    args = [exiftool_path, "-overwrite_original"]
    args.append("-{}={}".format(tag, names[0]))
    for name in names[1:]:
        args.append("-{}+={}".format(tag, name))
    args.append(image_path)

    result = subprocess.run(
        args,
        shell=False,
        capture_output=True,
        text=True,
        timeout=30,
        cwd=os.path.dirname(os.path.abspath(exiftool_path)),
    )
    return result.returncode, result.stdout.strip(), result.stderr.strip()


# ---------------------------------------------------------------------------
# Main application
# ---------------------------------------------------------------------------

class App(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title("Photo ID Tagger")
        self.minsize(600, 400)
        self.resizable(True, True)
        self._build_ui()

    def _build_ui(self):
        pad = {"padx": 8, "pady": 4}

        # --- Input frame ---
        frm = ttk.Frame(self)
        frm.pack(fill="x", padx=12, pady=8)
        frm.columnconfigure(1, weight=1)

        ttk.Label(frm, text="ExifTool:").grid(row=0, column=0, sticky="w", **pad)
        self.exiftool_var = tk.StringVar(value=find_exiftool() or "")
        ttk.Entry(frm, textvariable=self.exiftool_var).grid(row=0, column=1, sticky="ew", **pad)
        ttk.Button(frm, text="Browse…", command=self._browse_exiftool).grid(row=0, column=2, **pad)

        ttk.Label(frm, text="CSV File:").grid(row=1, column=0, sticky="w", **pad)
        self.csv_var = tk.StringVar()
        ttk.Entry(frm, textvariable=self.csv_var).grid(row=1, column=1, sticky="ew", **pad)
        ttk.Button(frm, text="Browse…", command=self._browse_csv).grid(row=1, column=2, **pad)

        ttk.Label(frm, text="Image Folder:").grid(row=2, column=0, sticky="w", **pad)
        self.folder_var = tk.StringVar()
        ttk.Entry(frm, textvariable=self.folder_var).grid(row=2, column=1, sticky="ew", **pad)
        ttk.Button(frm, text="Browse…", command=self._browse_folder).grid(row=2, column=2, **pad)

        # --- Run button ---
        self.run_btn = ttk.Button(self, text="Run", command=self._on_run)
        self.run_btn.pack(pady=4)

        # --- Log area ---
        ttk.Separator(self).pack(fill="x", padx=12)
        ttk.Label(self, text="Log:", anchor="w").pack(fill="x", padx=12, pady=(6, 0))
        self.log = scrolledtext.ScrolledText(self, state="disabled", wrap="word", height=16)
        self.log.pack(fill="both", expand=True, padx=12, pady=(0, 12))

    def _browse_exiftool(self):
        path = filedialog.askopenfilename(
            title="Locate exiftool.exe",
            filetypes=[("ExifTool", "exiftool.exe"), ("Executable", "*.exe"), ("All files", "*.*")],
        )
        if path:
            self.exiftool_var.set(path)

    def _browse_csv(self):
        path = filedialog.askopenfilename(
            title="Select CSV export file",
            filetypes=[("CSV files", "*.csv"), ("All files", "*.*")],
        )
        if path:
            self.csv_var.set(path)

    def _browse_folder(self):
        path = filedialog.askdirectory(title="Select image folder")
        if path:
            self.folder_var.set(path)

    def _log(self, msg):
        """Append a line to the log widget (must be called from main thread)."""
        self.log.config(state="normal")
        self.log.insert("end", msg + "\n")
        self.log.see("end")
        self.log.config(state="disabled")

    def _log_threadsafe(self, msg):
        """Append a log line from any thread."""
        self.after(0, lambda m=msg: self._log(m))

    def _on_run(self):
        # Clear log
        self.log.config(state="normal")
        self.log.delete("1.0", "end")
        self.log.config(state="disabled")

        csv_path = self.csv_var.get().strip()
        folder   = self.folder_var.get().strip()
        exiftool = self.exiftool_var.get().strip()

        if not csv_path or not folder:
            self._log("ERROR: Please select both a CSV file and an image folder.")
            return

        if not os.path.isfile(csv_path):
            self._log("ERROR: CSV file not found: " + csv_path)
            return

        if not os.path.isdir(folder):
            self._log("ERROR: Image folder not found: " + folder)
            return

        if not exiftool:
            self._log("ERROR: ExifTool not found. Use the Browse button to locate exiftool.exe.")
            self._log("       Download from: https://exiftool.org")
            return

        if not os.path.isfile(exiftool):
            self._log("ERROR: ExifTool not found at: " + exiftool)
            return

        if "(-k)" in os.path.basename(exiftool):
            self._log("ERROR: You selected exiftool(-k).exe — this version waits for a keypress and cannot be used by this script.")
            self._log("       Fix: rename exiftool(-k).exe to exiftool.exe — that is all you need to do.")
            self._log("       Or download the plain exiftool.exe from https://exiftool.org (Windows Executable link).")
            return

        self.run_btn.config(state="disabled")
        t = threading.Thread(target=self._run_job, args=(csv_path, folder, exiftool), daemon=True)
        t.start()

    def _run_job(self, csv_path, folder, exiftool):
        log = self._log_threadsafe

        log("ExifTool: " + exiftool)
        log("CSV:      " + csv_path)
        log("Folder:   " + folder)
        log("-" * 60)

        ok = skip = errors = 0
        folder_index = build_folder_index(folder)

        try:
            with open(csv_path, newline="", encoding="utf-8-sig") as f:
                reader = csv.DictReader(f)
                rows = list(reader)
        except Exception as e:
            log("ERROR: Could not read CSV — " + str(e))
            self.after(0, lambda: self.run_btn.config(state="normal"))
            return

        for row in rows:
            filename    = row.get("filename", "").strip()
            people_raw  = row.get("people",   "").strip()

            if not filename:
                continue

            if not people_raw:
                log("SKIP: {} — no names listed".format(filename))
                skip += 1
                continue

            image_path = find_image(folder, filename, folder_index)
            if not image_path:
                log("SKIP: {} — not found in image folder".format(filename))
                skip += 1
                continue

            names = [n.strip() for n in people_raw.split(",") if n.strip()]
            if not names:
                log("SKIP: {} — no names after parsing".format(filename))
                skip += 1
                continue

            try:
                code, stdout, stderr = run_exiftool(exiftool, image_path, names)
            except subprocess.TimeoutExpired:
                log("ERROR: {} — ExifTool timed out".format(filename))
                errors += 1
                continue
            except Exception as e:
                log("ERROR: {} — {}".format(filename, str(e)))
                errors += 1
                continue

            if code == 0:
                actual_name = os.path.basename(image_path)
                match_note = " (matched as {})".format(actual_name) if actual_name != filename else ""
                log("OK:    {}{} — {} person(s) tagged: {}".format(
                    filename, match_note, len(names), ", ".join(names)))
                ok += 1
            else:
                log("ERROR: {} — ExifTool exit code {}: {}".format(
                    filename, code, stderr or stdout))
                errors += 1

        log("-" * 60)
        log("Done.  {} tagged,  {} skipped,  {} error(s).".format(ok, skip, errors))
        self.after(0, lambda: self.run_btn.config(state="normal"))


if __name__ == "__main__":
    App().mainloop()

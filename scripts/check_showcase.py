#!/usr/bin/env python3
"""Checks for the public showcase repository.

- Every image and relative link in the Markdown files points at a file that exists.
- The repository holds no source code, configuration or data files (the code stays private).
- Screenshots stay light enough to load quickly on GitHub.
"""
import pathlib
import re
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parents[1]
MAX_IMAGE_BYTES = 1_000_000
ALLOWED = {'.md', '.webp', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.yml', '.py', ''}
ALLOWED_PY = {'scripts/check_showcase.py'}

files = [p for p in subprocess.check_output(['git', 'ls-files'], cwd=ROOT, text=True).splitlines() if p]
errors = []

for name in files:
    path = pathlib.PurePosixPath(name)
    suffix = path.suffix.lower()
    if suffix not in ALLOWED or (suffix == '.py' and name not in ALLOWED_PY) \
            or (suffix == '.yml' and not name.startswith('.github/workflows/')):
        errors.append(f'{name}: not expected in the public showcase (the code stays private)')
    if path.name.startswith('.env') or path.name in {'local.config.php', 'composer.json', 'package.json'}:
        errors.append(f'{name}: configuration must never be published')
    if suffix in {'.webp', '.png', '.jpg', '.jpeg', '.gif'}:
        size = (ROOT / name).stat().st_size
        if size > MAX_IMAGE_BYTES:
            errors.append(f'{name}: {size // 1024} KB, keep screenshots under {MAX_IMAGE_BYTES // 1000} KB')

link = re.compile(r'!?\[[^\]]*\]\(([^)\s]+)\)|<img[^>]*\ssrc="([^"]+)"')
for name in files:
    if not name.endswith('.md'):
        continue
    text = (ROOT / name).read_text(encoding='utf-8')
    for match in link.finditer(text):
        target = (match.group(1) or match.group(2)).split('#')[0]
        if not target or re.match(r'^[a-z]+:', target):
            continue
        resolved = (ROOT / name).parent / target
        if not resolved.exists():
            errors.append(f'{name}: broken link to {target}')

if errors:
    print('\n'.join(f'FAIL: {e}' for e in errors))
    sys.exit(1)
print(f'PASS: {len(files)} files checked; links resolve, no code or configuration, images under {MAX_IMAGE_BYTES // 1000} KB.')

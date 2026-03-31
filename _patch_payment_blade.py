#!/usr/bin/env python3
from pathlib import Path

path = Path("resources/views/admin/report/payment.blade.php")
s = path.read_text(encoding="utf-8")
# ... truncated for test

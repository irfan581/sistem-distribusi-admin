#!/usr/bin/env bash
#
# Skrip deploy sekarang tinggal di scripts/deploy.sh, satu tingkat di atas,
# karena ia mengurus backend dan frontend sekaligus — bukan hanya backend.
#
# Berkas ini dibiarkan hidup supaya `./deploy.sh` dari dalam backend/ tetap
# jalan seperti biasa. Semua argumen diteruskan apa adanya, termasuk --paksa
# dan --lewati-backup.
#
set -euo pipefail

exec bash "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/scripts/deploy.sh" "$@"

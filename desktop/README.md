# TMK Admin Desktop

Electron thin-shell that opens the existing web admin (`https://example.com`)
in a native window. No backend/data changes — the app just wraps the live
site, so updates to the web admin appear automatically without rebuilding
the installer.

## Develop / run locally

```bash
cd desktop
npm install
npm start
```

To point at a different URL (e.g. local dev server) without editing code:

```bash
TMK_APP_URL=http://localhost:5173 npm start
```

## Build installers (no publish)

```bash
# Windows .exe — builds fine from this Mac via electron-builder's bundled
# wine, no Windows machine needed
npm run dist:win

# macOS .dmg (for local testing on this Mac)
npm run dist:mac
```

Output lands in `desktop/dist/`. These builds are unsigned, so Windows
SmartScreen shows an "Unknown Publisher" warning on first run — expected
for an internal tool without a paid code-signing certificate.

## Auto-update

The app checks GitHub Releases on startup (`electron-updater`) and installs
new versions on next quit. Releases live in the existing private repo
(`irfan581/tani-makmur-admin`), so both build time (to publish) and runtime
(for end-user machines to download) need a GitHub token — set up once:

1. Create a **fine-grained PAT** at github.com/settings/tokens, scoped to
   only the `tani-makmur-admin` repo, permission **Contents: Read-only**.
2. Copy `update-token.example.js` to `update-token.js` and paste the token
   in. This file is gitignored and gets bundled into the packaged app so
   installed copies can authenticate to the private release feed.

To ship a new version:

```bash
# bump "version" in package.json first
npm run release:win
```

This builds the installer and publishes it (+ `latest.yml`) as a GitHub
Release, tagged from `package.json`'s version. Installed apps pick it up
automatically next time they start.

## Icons

`icons/icon.png` is the TMK logo mark (from `frontend/public/logo-icon.png`),
padded onto a transparent square canvas. electron-builder converts it to
`.ico`/`.icns` automatically at build time — replace this one file if the
logo changes, no need to hand-produce `.ico`/`.icns`.

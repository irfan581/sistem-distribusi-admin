// Copy this file to update-token.js and fill in a fine-grained GitHub PAT
// scoped to ONLY this repo, permission "Contents: Read-only". It lets the
// shipped app read private GitHub Releases to check for/download updates.
// update-token.js is gitignored — never commit a real token.
module.exports = {
  GH_TOKEN: '',
};

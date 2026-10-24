# GIT-WORKFLOW

Hosting repo: **GitHub** (bukan GitLab). Agen tidak melakukan `git push`; pemilik repo yang push. Tidak ada remote GitLab, `.gitlab-ci.yml`, atau tautan GitLab.

## Branch

- `main` selalu hijau (test + lint + build) dan bisa didemo.
- Pekerjaan di branch pendek berawalan: `feat/`, `fix/`, `docs/`, `refactor/`, `test/`, `chore/`.
- Cabang worktree subagent mengikuti pola yang sama, mis. `feat/be-domain`, `feat/be-provider-scheduler`, `feat/fe-design-system`, `feat/fe-peta`, `feat/fe-pipeline`, `feat/fe-dashboard-master`, `test/qa-e2e`.
- Merge ke `main` dengan `git merge --no-ff` setelah test + lint + build hijau, agar tiap milestone terlihat di riwayat.

## Commit

Conventional commits: `feat`, `fix`, `docs`, `refactor`, `test`, `chore`, `perf`, `ci`. Kecil dan fokus, satu tujuan per commit. Pesan commit dalam Bahasa Inggris. Jangan commit file besar, generated, atau secret.

Contoh: `feat(scoring): add neighbor-effect component to prospect score`.

## Tag

| Tag | Akhir |
|---|---|
| `v0.1-docs` | Sesi 1 |
| `v0.2-backend` | Sesi 2 |
| `v0.3-app` | Sesi 3 |
| `v1.0.0` | Sesi 4 |

## Berkas repo

- `.gitignore`: `vendor`, `node_modules`, `.env`, `database/*.sqlite`, `storage` (isi runtime), `public/build`, `playwright-report`, `test-results`, `coverage`.
- `.gitattributes`: normalisasi line ending (`* text=auto eol=lf`).
- `.github/workflows/ci.yml` (dibuat di Fase 5.0): trigger push dan PR ke `main`. Tiga job:
  1. **backend**: setup PHP (versi dipin, `coverage: pcov`), `composer install` ber-cache, Pint, Pest dengan cakupan.
  2. **frontend**: Node (versi dipin), `npm ci` ber-cache, ESLint, Vitest, build.
  3. **e2e**: install browser Playwright, migrate + seed deterministik (MockProvider), build, `npm run e2e`; unggah `playwright-report` dan screenshot sebagai artifact bila gagal.
  Versi aksi dipin ke tag/SHA; matriks seperlunya.
- Scheduler berjalan lewat Laravel; tidak ada workflow GitHub Actions untuk sync.
- `.github/pull_request_template.md` (ringkasan, jenis perubahan, cara uji, checklist: test, lint, build, dokumen, screenshot).
- `.github/ISSUE_TEMPLATE/` (bug, fitur).
- `.github/dependabot.yml` (composer, npm, github-actions; mingguan).
- Badge CI di README dengan placeholder `USERNAME`.

## Simulasi alur PR lokal

Untuk tiap milestone besar: kerja di branch → tulis ringkasan PR dari template ke `docs/pr/NN-judul.md` → merge `--no-ff`. Dokumen ini menjadi bukti proses untuk slide.

## Langkah pemilik

1. Buat repo GitHub kosong.
2. `git remote add origin git@github.com:USERNAME/pos-merchant-hunter.git`
3. `git push -u origin main --tags`
4. Set repo public atau tambahkan trainer sebagai collaborator.
5. Aktifkan GitHub Actions; pastikan CI hijau di tab Actions.

## Keamanan repo

Tidak ada secret di riwayat. Sebelum tag `v1.0.0`: pindai dengan gitleaks bila ada, atau `git grep`/`git log -p` untuk pola kunci (`AKIA`, `sk-ant-`, `AIza`, `BEGIN PRIVATE KEY`, `APP_KEY=base64:`), lalu laporkan hasilnya.

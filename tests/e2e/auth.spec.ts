import { test, expect, freshData, loginAs, pickContactable } from './support/fixtures';
import { openMap } from './support/map';
import { PASSWORD, USERS } from './support/env';

freshData();

const PROTECTED_PAGES = ['/', '/pipeline', '/dashboard', '/impact', '/master/data-sources', '/master/outlets', '/master/categories', '/master/settings', '/scheduler'];

test('1. tamu dialihkan ke halaman login di semua halaman terproteksi', async ({ page }) => {
    for (const path of PROTECTED_PAGES) {
        await page.goto(path);
        await expect(page, `${path} must redirect guests`).toHaveURL(/\/login$/);
        await expect(page.getByRole('heading', { name: /POS Merchant/ })).toBeVisible();
    }
});

test('1. login salah menampilkan galat, login benar masuk ke peta, logout kembali ke login', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill(USERS.ayu.email);
    await page.getByLabel('Kata sandi').fill('salah-total-123');
    await page.getByRole('button', { name: 'Masuk' }).click();
    await expect(page.getByRole('alert')).toContainText('Email atau kata sandi salah');
    await expect(page).toHaveURL(/\/login$/);

    await page.getByLabel('Kata sandi').fill(PASSWORD);
    await page.getByRole('button', { name: 'Masuk' }).click();
    await expect(page).toHaveURL(/\/(\?.*)?$/);
    await expect(page.getByTestId('map-canvas')).toBeVisible();
    await expect(page.getByRole('button', { name: `Menu pengguna, ${USERS.ayu.name}` })).toBeVisible();

    // A logged-in user is kept away from the login page.
    await page.goto('/login');
    await expect(page).not.toHaveURL(/\/login$/);

    await page.getByRole('button', { name: `Menu pengguna, ${USERS.ayu.name}` }).click();
    await page.getByRole('menuitem', { name: 'Keluar' }).click();
    await expect(page).toHaveURL(/\/login$/);

    // Session is really gone: protected pages redirect again.
    await page.goto('/pipeline');
    await expect(page).toHaveURL(/\/login$/);
});

test('menu pengguna dan tombol Keluar tidak tertutup drawer prospek', async ({ page, context }) => {
    await loginAs(context, 'ayu');
    await openMap(page, `prospect=${pickContactable(0).id}`);
    await expect(page.getByRole('dialog')).toBeVisible();

    await page.getByRole('button', { name: /Menu pengguna/ }).click();
    // A plain click only succeeds when nothing (e.g. the drawer) sits on top of the button.
    await page.getByRole('menuitem', { name: 'Keluar' }).click();
    await expect(page).toHaveURL(/\/login$/);
});

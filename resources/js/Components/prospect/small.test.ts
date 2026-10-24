import { beforeEach, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import ScoreBreakdown from './ScoreBreakdown.vue';
import SimulasiBadge from './SimulasiBadge.vue';
import WhatsAppButton from './WhatsAppButton.vue';
import ProspectCard from './ProspectCard.vue';
import { cardFixture, mockMatchMedia, prospectFixture } from '@/lib/testing';

beforeEach(() => mockMatchMedia(false));

describe('ScoreBreakdown', () => {
    it('renders a row per component with points, max and reason', () => {
        const w = mount(ScoreBreakdown, { props: { components: prospectFixture().score_breakdown } });
        const rows = w.findAll('[data-testid="score-row"]');
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('Kecocokan kategori');
        expect(rows[0].text()).toContain('20,0');
        expect(rows[0].text()).toContain('25');
        expect(rows[0].text()).toContain('Kafe: bobot 80');
        expect(rows[0].find('[role="meter"]').attributes('aria-valuenow')).toBe('20');
    });
    it('shows a placeholder when empty', () => {
        const w = mount(ScoreBreakdown, { props: { components: [] } });
        expect(w.text()).toContain('belum tersedia');
    });
});

describe('SimulasiBadge', () => {
    it('is a labelled focusable badge with a tooltip on hover', async () => {
        const w = mount(SimulasiBadge, { props: { field: 'Telepon' } });
        const badge = w.get('[data-testid="simulasi-badge"]');
        expect(badge.text()).toBe('Simulasi');
        expect(badge.attributes('tabindex')).toBe('0');
        expect(badge.attributes('aria-label')).toContain('simulasi');
        expect(w.find('[role="tooltip"]').exists()).toBe(false);
        await w.trigger('mouseenter');
        expect(w.get('[role="tooltip"]').text()).toContain('bukan data nyata');
    });
});

describe('WhatsAppButton', () => {
    const real = { phone: '0812-5555-0101', whatsappUrl: 'https://wa.me/6281255550101' };

    it('renders an enabled link with the url', () => {
        const w = mount(WhatsAppButton, { props: real });
        const a = w.get('[data-testid="wa-button"]');
        expect(a.element.tagName).toBe('A');
        expect(a.attributes('href')).toBe(real.whatsappUrl);
        expect(a.attributes('rel')).toContain('noopener');
        expect(w.find('[data-testid="wa-reason"]').exists()).toBe(false);
    });

    it('intercepts the click when asked and emits it', async () => {
        const w = mount(WhatsAppButton, { props: { ...real, intercept: true } });
        await w.get('a').trigger('click');
        expect(w.emitted('click')).toHaveLength(1);
    });

    it.each([
        [{ doNotContact: true }, 'Jangan hubungi'],
        [{ lifecycleStatus: 'closed' as const }, 'sudah tutup'],
        [{ phone: null, whatsappUrl: null }, 'Tidak ada nomor'],
        [{ phone: '0999-1111-2222', phoneSimulated: true, whatsappUrl: null }, 'Nomor simulasi'],
        [{ phone: '021-5551234', whatsappUrl: null }, 'tidak valid'],
    ])('is disabled with a reason (%o)', (extra, reason) => {
        const w = mount(WhatsAppButton, { props: { ...real, ...extra } });
        const btn = w.get('[data-testid="wa-button"]');
        expect(btn.element.tagName).toBe('BUTTON');
        expect(btn.attributes('disabled')).toBeDefined();
        expect(btn.attributes('aria-describedby')).toBe(w.get('[data-testid="wa-reason"]').attributes('id'));
        expect(w.get('[data-testid="wa-reason"]').text()).toContain(reason);
    });
});

describe('ProspectCard', () => {
    it('shows name, category, city, score and badges', () => {
        const w = mount(ProspectCard, { props: { prospect: cardFixture() } });
        expect(w.text()).toContain('Kopi Senja');
        expect(w.text()).toContain('Kafe · Jakarta Pusat');
        expect(w.text()).toContain('Baru buka');
        expect(w.text()).toContain('Simulasi');
        expect(w.get('[role="img"]').attributes('aria-label')).toContain('Skor 82');
    });

    it('emits select with the id and shows closed/opt-out/no-phone states', async () => {
        const w = mount(ProspectCard, { props: { prospect: cardFixture({ badges: ['closed', 'opt_out'], phone_present: false, do_not_contact: true }) } });
        expect(w.text()).toContain('Tutup');
        expect(w.text()).toContain('Jangan hubungi');
        expect(w.find('[data-testid="no-phone"]').exists()).toBe(true);
        await w.trigger('click');
        expect(w.emitted('select')?.[0]).toEqual([101]);
    });
});

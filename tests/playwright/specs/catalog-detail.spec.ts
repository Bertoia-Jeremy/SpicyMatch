import { test, expect } from '@playwright/test';

const pages = [
  '/fr/methodes-preparation/infusion',
  '/en/preparation-methods/whole-use',
  '/es/metodos-preparacion/asado-a-la-brasa-o-al-horno',
  '/fr/epices/composes-aromatiques/carvacrol',
  '/en/spices/aromatic-compounds/s-carvone',
  '/fr/epices/composes-aromatiques/isothiocyanate-de-6-methylsulfinyl-hexyle',
  '/es/especias/compuestos-aromaticos/isotiocianato-de-6-metilsulfinil-hexilo',
  '/fr/epices/saveurs-aromatiques/herbace-sauvage',
  '/en/spices/aromatic-flavors/earthy-golden',
  '/es/especias/sabores-aromaticos/pimentado-y-almizclado',
  '/fr/epices/cannelle',
  '/en/spices/cumin',
  '/es/especias/canela-de-ceilan',
  '/fr/epices/vanille',
  '/fr/epices/groupes-aromatiques/capsaicinoides-alcaloides',
  '/fr/epices/groupes-aromatiques/a-reviser',
  '/en/spices/aromatic-groups/phenylpropanoids',
  '/es/especias/grupos-aromaticos/capsaicinoides-y-alcaloides',
  '/fr/epices/types-epices/graine',
  '/fr/epices/types-epices/rhizome-racine',
  '/en/spices/spice-types/flower-stigma',
  '/es/especias/tipos-especias/rizoma-raiz',
  '/fr/faq',
  '/en/faq',
  '/es/faq',
  '/fr/plan-du-site',
  '/en/site-map',
  '/es/mapa-del-sitio',
];

const widths = [360, 375, 768, 1024, 1280];

test.describe('Catalog detail pages are mobile first', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => localStorage.setItem('sm_tours', JSON.stringify({ '*': 1 })));
  });

  for (const path of pages) {
    for (const width of widths) {
      test(`${path} @${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 800 });
        await page.goto(path);

        const audit = await page.evaluate(() => {
          const root = document.documentElement;
          const visible = (el: Element) => (el as HTMLElement).offsetParent !== null;
          const smallText = [...document.querySelectorAll('main *')]
            .filter(el => visible(el) && [...el.childNodes].some(n => n.nodeType === Node.TEXT_NODE && n.textContent?.trim()))
            .filter(el => parseFloat(getComputedStyle(el).fontSize) < 12)
            .map(el => el.className);
          const smallTargets = [...document.querySelectorAll('main .spice-row-link, main summary, main .detail-crumbs a, main .ref-chip, main .flav-step-link, main .spc-anchor, main .spc-compound, main .spc-prep-method, main .spc-cta, main .fam-molecule, main .fam-filter, main .fam-other, main a.kind-bucket-link, main .kind-filter, main .kind-other, main .faq-trigger, main .faq-filter, main .faq-contact-btn, main .plan-link')]
            .filter(visible)
            .filter(el => el.getBoundingClientRect().height < 44)
            .map(el => el.textContent?.trim());
          const h1 = document.querySelector('main h1') as HTMLElement;
          const lines = Math.round(h1.getBoundingClientRect().height / parseFloat(getComputedStyle(h1).lineHeight));

          return { overflow: root.scrollWidth - root.clientWidth, smallText, smallTargets, lines };
        });

        expect(audit.overflow).toBeLessThanOrEqual(0);
        expect(audit.smallText).toEqual([]);
        expect(audit.smallTargets).toEqual([]);
        if (width <= 375) {
          expect(audit.lines).toBeLessThanOrEqual(2);
        }
      });
    }
  }
});

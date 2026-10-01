import { mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import subsetFont from 'subset-font';

const FA_ROOT = 'node_modules/@fortawesome/fontawesome-free';
const OUTPUT = 'public/lib/fontawesome-subset';
const TOKEN_FILES = process.argv.length > 2 ? process.argv.slice(2) : ['var/fontawesome-tokens.json'];

const FONTS = {
    solid: { file: 'fa-solid-900', family: 'Font Awesome 6 Free', weight: 900 },
    regular: { file: 'fa-regular-400', family: 'Font Awesome 6 Free', weight: 400 },
    brands: { file: 'fa-brands-400', family: 'Font Awesome 6 Brands', weight: 400 },
};

const ICON_RULE = /(?:\.fa-[a-z0-9-]+,?)+\{--fa:"[^"]*"\}/g;
const FONT_FACE = /@font-face\{[^}]*\}/g;

const metadata = JSON.parse(await readFile(`${FA_ROOT}/metadata/icon-families.json`, 'utf8'));
const { version } = JSON.parse(await readFile(`${FA_ROOT}/package.json`, 'utf8'));

const catalogue = new Map();
for (const [name, icon] of Object.entries(metadata)) {
    const styles = (icon.familyStylesByLicense?.free ?? [])
        .filter((entry) => entry.family === 'classic' && entry.style in FONTS)
        .map((entry) => entry.style);
    if (styles.length === 0) {
        continue;
    }
    const entry = { glyph: String.fromCodePoint(parseInt(icon.unicode, 16)), styles };
    catalogue.set(name, entry);
    for (const alias of icon.aliases?.names ?? []) {
        catalogue.set(alias, entry);
    }
}

const tokenSet = new Set();
for (const file of TOKEN_FILES) {
    for (const token of JSON.parse(await readFile(file, 'utf8')).tokens) {
        tokenSet.add(token);
    }
}
const tokens = [...tokenSet].sort();
const icons = tokens.filter((token) => catalogue.has(token));
const ignored = tokens.filter((token) => !catalogue.has(token));
const used = new Set(icons);

const byStyle = Object.fromEntries(Object.keys(FONTS).map((style) => [style, []]));
for (const token of icons) {
    for (const style of catalogue.get(token).styles) {
        byStyle[style].push(token);
    }
}

await rm(OUTPUT, { recursive: true, force: true });
await mkdir(`${OUTPUT}/webfonts`, { recursive: true });
await mkdir(`${OUTPUT}/css`, { recursive: true });

const fontFaces = [];
for (const [style, font] of Object.entries(FONTS)) {
    if (byStyle[style].length === 0) {
        continue;
    }
    const glyphs = [...new Set(byStyle[style].map((token) => catalogue.get(token).glyph))].join('');
    const source = await readFile(`${FA_ROOT}/webfonts/${font.file}.ttf`);
    await writeFile(`${OUTPUT}/webfonts/${font.file}.woff2`, await subsetFont(source, glyphs, { targetFormat: 'woff2' }));
    fontFaces.push(`@font-face{font-family:"${font.family}";font-style:normal;font-weight:${font.weight};font-display:block;src:url(../webfonts/${font.file}.woff2) format("woff2")}`);
}

const css = (await readFile(`${FA_ROOT}/css/all.min.css`, 'utf8'))
    .replace(FONT_FACE, '')
    .replace(ICON_RULE, (rule) => {
        const selectors = rule.slice(0, rule.indexOf('{')).split(',').map((selector) => selector.slice(4));
        return selectors.some((selector) => used.has(selector)) ? rule : '';
    });

await writeFile(`${OUTPUT}/css/fontawesome.min.css`, css + fontFaces.join(''));
await writeFile(`${OUTPUT}/manifest.json`, JSON.stringify({ version, styles: byStyle, ignored }, null, 4) + '\n');

console.log(`Font Awesome ${version} subset: ${icons.length} icons, ${ignored.length} ignored tokens -> ${OUTPUT}`);

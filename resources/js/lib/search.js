/**
 * Ranking for the searchable pickers.
 *
 * Tiers (lower is better):
 *   0 exact label match                     "laravel developer" -> Laravel Developer
 *   1 label starts with the query as words  "java"     -> Java Developer
 *   2 label starts with the query           "java"     -> JavaScript Developer
 *   3 the query appears as whole word(s)    "engineer" -> Civil Engineer
 *   4 a word starts with the query          "engineer" -> Civil Engineering (Undergraduate)
 *   5 every typed word starts a word        "dev lar"  -> Laravel Developer
 *   6 every typed word is in the label (anywhere)
 *   7 matches only through the category name ("healthcare")
 * Ties: lower `rank` first (popularity, from the server), then alphabetical.
 */

const WORD_CHAR = /[\p{L}\p{N}]/u;
const WORD_SPLIT = /[^\p{L}\p{N}]+/u;

export function normalize(text) {
    return String(text ?? '')
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

/**
 * Find the query in the text starting at a word boundary.
 * @returns {'whole'|'prefix'|null} 'whole' when it also ends at a word boundary.
 */
function wordMatch(text, query) {
    let found = null;
    for (let index = text.indexOf(query); index !== -1; index = text.indexOf(query, index + 1)) {
        if (index > 0 && WORD_CHAR.test(text[index - 1])) {
            continue;
        }
        const next = text[index + query.length];
        if (next === undefined || !WORD_CHAR.test(next)) {
            return 'whole';
        }
        found = 'prefix';
    }
    return found;
}

/**
 * @returns {number|null} the match tier, or null when the option does not match.
 */
export function matchTier(label, group, query) {
    const q = normalize(query);
    if (!q) {
        return 0;
    }

    const text = normalize(label);
    const terms = q.split(' ');
    const words = text.split(WORD_SPLIT).filter(Boolean);

    if (text === q) return 0;
    if (text.startsWith(q)) {
        const next = text[q.length];
        return next === undefined || !WORD_CHAR.test(next) ? 1 : 2;
    }

    const inWords = wordMatch(text, q);
    if (inWords === 'whole') return 3;
    if (inWords === 'prefix') return 4;
    if (terms.every((term) => words.some((word) => word.startsWith(term)))) return 5;
    if (terms.every((term) => text.includes(term))) return 6;

    const withGroup = `${text} ${normalize(group)}`;
    if (terms.every((term) => withGroup.includes(term))) return 7;

    return null;
}

/**
 * Filter and order options for a query. Options are { label, group?, rank? }.
 */
export function rankOptions(options, query) {
    return options
        .map((option, index) => ({ option, index, tier: matchTier(option.label, option.group, query) }))
        .filter((entry) => entry.tier !== null)
        .sort(
            (a, b) =>
                a.tier - b.tier ||
                (a.option.rank ?? Number.MAX_SAFE_INTEGER) - (b.option.rank ?? Number.MAX_SAFE_INTEGER) ||
                a.option.label.localeCompare(b.option.label) ||
                a.index - b.index,
        )
        .map((entry) => entry.option);
}

/**
 * Split a label around the first word-boundary (else any) occurrence of the query, for highlighting.
 * @returns {[string, string, string]} [before, match, after]; match is '' when there is none.
 */
export function splitMatch(label, query) {
    const q = normalize(query);
    if (!q) {
        return [label, '', ''];
    }

    // normalize() can change string length (e.g. combining marks), so only highlight when lengths line up.
    const text = normalize(label);
    if (text.length !== label.length) {
        return [label, '', ''];
    }

    let index = -1;
    for (let i = text.indexOf(q); i !== -1; i = text.indexOf(q, i + 1)) {
        if (i === 0 || !WORD_CHAR.test(text[i - 1])) {
            index = i;
            break;
        }
    }
    if (index === -1) {
        index = text.indexOf(q);
    }
    if (index === -1) {
        return [label, '', ''];
    }

    return [label.slice(0, index), label.slice(index, index + q.length), label.slice(index + q.length)];
}

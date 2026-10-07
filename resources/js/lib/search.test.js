import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { matchTier, normalize, rankOptions, splitMatch } from './search.js';

const topic = (label, group = 'Software Development', rank) => ({ label, group, rank });
const labels = (options) => options.map((option) => option.label);

describe('matchTier', () => {
    it('orders exact, whole-word, prefix, multi-word, substring, and category matches', () => {
        assert.equal(matchTier('Laravel Developer', 'x', 'laravel developer'), 0);
        assert.equal(matchTier('Java Developer', 'x', 'java'), 1);
        assert.equal(matchTier('JavaScript Developer', 'x', 'java'), 2);
        assert.equal(matchTier('Registered Nurse', 'x', 'nurse'), 3);
        assert.equal(matchTier('Big Data Engineer', 'x', 'data engineer'), 3);
        assert.equal(matchTier('Civil Engineering (Undergraduate)', 'x', 'engineer'), 4);
        assert.equal(matchTier('Laravel Developer', 'x', 'dev lar'), 5);
        assert.equal(matchTier('JavaScript Developer', 'x', 'script'), 6);
        assert.equal(matchTier('Midwife', 'Healthcare', 'health'), 7);
        assert.equal(matchTier('Midwife', 'Healthcare', 'plumbing'), null);
    });

    it('ignores case, extra spaces, and accents', () => {
        assert.equal(matchTier('Café Manager', 'x', '  CAFE   man '), 2);
        assert.equal(normalize('  Café\tManager '), 'cafe manager');
    });

    it('treats punctuation as word boundaries', () => {
        assert.equal(matchTier('QA / Software Test Engineer', 'x', 'software'), 3);
        assert.equal(matchTier('.NET / C# Developer', 'x', 'net'), 3);
    });
});

describe('rankOptions', () => {
    const catalog = [
        topic('Accounting & Finance (Undergraduate)', 'Courses & Study Programs'),
        topic('Accountant', 'Business & Finance'),
        topic('Internal Auditor', 'Business & Finance'),
        topic('Enrolled Nurse', 'Healthcare'),
        topic('Registered Nurse', 'Healthcare'),
        topic('Nursing (Diploma)', 'Courses & Study Programs'),
        topic('Java Developer'),
        topic('JavaScript Developer'),
        topic('Laravel Developer'),
    ];

    it('puts whole-word matches before partial-word ones', () => {
        const options = [topic('Civil Engineering (Undergraduate)'), topic('Civil Engineer'), topic('Data Engineer')];
        assert.deepEqual(labels(rankOptions(options, 'engineer')), [
            'Civil Engineer',
            'Data Engineer',
            'Civil Engineering (Undergraduate)',
        ]);
    });

    it('puts prefix matches before word-start and substring matches', () => {
        assert.deepEqual(labels(rankOptions(catalog, 'java')), ['Java Developer', 'JavaScript Developer']);
        assert.deepEqual(labels(rankOptions(catalog, 'acc')), ['Accountant', 'Accounting & Finance (Undergraduate)']);
        assert.deepEqual(labels(rankOptions(catalog, 'developer')).slice(0, 1), ['Java Developer']);
    });

    it('ranks an exact match first', () => {
        assert.equal(rankOptions(catalog, 'javascript developer')[0].label, 'JavaScript Developer');
    });

    it('breaks ties by popularity, then alphabetically', () => {
        assert.deepEqual(labels(rankOptions(catalog, 'nurse')), ['Enrolled Nurse', 'Registered Nurse']);

        const withUsage = catalog.map((option) =>
            option.label === 'Registered Nurse' ? { ...option, rank: 0 } : { ...option, rank: 5 },
        );
        assert.deepEqual(labels(rankOptions(withUsage, 'nurse')), ['Registered Nurse', 'Enrolled Nurse']);
    });

    it('drops options that do not match', () => {
        assert.deepEqual(rankOptions(catalog, 'zzz'), []);
    });

    it('returns every option for an empty query', () => {
        assert.equal(rankOptions(catalog, '  ').length, catalog.length);
    });
});

describe('splitMatch', () => {
    it('highlights the word-start occurrence when there is one', () => {
        assert.deepEqual(splitMatch('Nursing Nurse Educator', 'nurse'), ['Nursing ', 'Nurse', ' Educator']);
        assert.deepEqual(splitMatch('Laravel Developer', 'LARA'), ['', 'Lara', 'vel Developer']);
    });

    it('returns the label untouched when nothing matches', () => {
        assert.deepEqual(splitMatch('Midwife', 'health'), ['Midwife', '', '']);
    });
});

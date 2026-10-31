import assert from 'node:assert/strict';
import test from 'node:test';

import { toggleEligibleSelection } from '../../resources/js/lib/bulkSelection.ts';

test('selects eligible IDs and preserves selections from other pages, then deselects this page', () => {
    const pageEligible = ['encrypted-a', 'encrypted-b'];
    const selectedOnOtherPage = ['encrypted-other-page'];

    const selected = toggleEligibleSelection(selectedOnOtherPage, pageEligible);
    assert.deepEqual(selected, [...selectedOnOtherPage, ...pageEligible]);

    const deselected = toggleEligibleSelection(selected, pageEligible);
    assert.deepEqual(deselected, selectedOnOtherPage);
});

test('adds all eligible IDs when the page selection is partial and preserves unrelated selections', () => {
    assert.deepEqual(
        toggleEligibleSelection(
            ['encrypted-a', 'encrypted-other-page'],
            ['encrypted-a', 'encrypted-b'],
        ),
        ['encrypted-a', 'encrypted-other-page', 'encrypted-b'],
    );
});

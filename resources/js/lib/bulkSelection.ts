export function toggleEligibleSelection(
    selectedIds: string[],
    eligibleIds: string[],
): string[] {
    const selected = new Set(selectedIds);
    const deselectPage =
        eligibleIds.length > 0 && eligibleIds.every((id) => selected.has(id));

    eligibleIds.forEach((id) => {
        if (deselectPage) {
            selected.delete(id);
        } else {
            selected.add(id);
        }
    });

    return [...selected];
}

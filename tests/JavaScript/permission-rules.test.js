import { describe, expect, it } from 'vitest';
import PermissionRules from '../../assets/admin/permission-rules';

/**
 * The rules a role stores are the only thing this plugin persists about a permission, and the
 * grid is repainted from them rather than bound to them. Wildcards make the two directions
 * asymmetric -- granting on top of a blanket rule is free, taking something out of one costs the
 * rule -- so every test here is about what a pattern list looks like after an edit, never about
 * the DOM.
 */

/** Shaped like a real section: rows with different operations, one of them outside the CRUD set. */
const IDENTIFIERS = [
    'sylius.order.index',
    'sylius.order.show',
    'sylius.order.cancel',
    'sylius.order.resend_confirmation_email',
    'sylius.payment.index',
    'sylius.payment.complete',
    'sylius.product.index',
    'sylius.product.update',
];

const READ_OPERATIONS = ['index', 'show', 'view'];

const rules = (...patterns) => new PermissionRules(IDENTIFIERS, READ_OPERATIONS, patterns);

/** Sorted, because the order patterns are stored in is normalise()'s business and not each test's. */
const stored = (subject) => subject.normalise().patterns;

describe('what a pattern covers', () => {
    it('matches a wildcard at any segment', () => {
        expect(PermissionRules.matches('sylius.order.*', 'sylius.order.cancel')).toBe(true);
        expect(PermissionRules.matches('*.*.index', 'sylius.payment.index')).toBe(true);
        expect(PermissionRules.matches('sylius.order.*', 'sylius.payment.cancel')).toBe(false);
    });

    it('calls a pattern broad when it covers subjects that may not exist yet', () => {
        expect(PermissionRules.isBroad('*.*.*')).toBe(true);
        expect(PermissionRules.isBroad('sylius.*.index')).toBe(true);
        expect(PermissionRules.isBroad('sylius.order.*')).toBe(false);
    });
});

describe('granting', () => {
    it('collapses a row into its own rule once its last operation is ticked', () => {
        const subject = rules('sylius.payment.index');

        subject.toggleCell('sylius.payment.complete');

        expect(stored(subject)).toEqual(['sylius.payment.*']);
    });

    it('leaves a blanket rule standing, because adding never contradicts it', () => {
        const subject = rules('*.*.index');

        subject.toggleCell('sylius.order.cancel');

        expect(stored(subject)).toEqual(['*.*.index', 'sylius.order.cancel']);
    });
});

describe('taking something away from a rule broader than one row', () => {
    it('spends the row rule and keeps every other operation of that row', () => {
        const subject = rules('sylius.order.*');

        subject.toggleCell('sylius.order.cancel');

        expect(stored(subject)).toEqual([
            'sylius.order.index',
            'sylius.order.resend_confirmation_email',
            'sylius.order.show',
        ]);
    });

    it('pays out "everything" into one rule per row, losing only rows that do not exist yet', () => {
        const subject = rules('*.*.*');

        subject.toggleCell('sylius.order.cancel');

        expect(stored(subject)).toEqual([
            'sylius.order.index',
            'sylius.order.resend_confirmation_email',
            'sylius.order.show',
            'sylius.payment.*',
            'sylius.product.*',
        ]);
    });

    it('grants exactly what was granted before, minus the one cell', () => {
        const subject = rules('*.*.*');

        subject.toggleCell('sylius.order.cancel');

        const denied = IDENTIFIERS.filter((identifier) => !subject.granted(identifier));

        expect(denied).toEqual(['sylius.order.cancel']);
    });
});

describe('a set of cells spanning several rows', () => {
    /** The "all" of the column holding the operations that are each row's own. */
    const extras = ['sylius.order.cancel', 'sylius.order.resend_confirmation_email', 'sylius.payment.complete'];

    it('turns the whole set on when part of it is off', () => {
        const subject = rules();

        subject.toggleIdentifiers(extras);

        expect(stored(subject)).toEqual([
            'sylius.order.cancel',
            'sylius.order.resend_confirmation_email',
            'sylius.payment.complete',
        ]);
    });

    it('turns the whole set off when all of it is on, and touches nothing else', () => {
        const subject = rules('*.*.*');

        subject.toggleIdentifiers(extras);

        expect(extras.every((identifier) => !subject.granted(identifier))).toBe(true);
        expect(subject.granted('sylius.order.index')).toBe(true);
        expect(subject.granted('sylius.payment.index')).toBe(true);
        expect(subject.granted('sylius.product.update')).toBe(true);
    });

    /** Partly granted counts as off: the toggle completes the set rather than clearing it. */
    it('turns the rest on when only part of the set is granted', () => {
        const subject = rules('sylius.order.*');

        subject.toggleIdentifiers(extras);

        expect(stored(subject)).toEqual(['sylius.order.*', 'sylius.payment.complete']);
    });

    it('spends a row rule once even though the row contributes two of the set', () => {
        const subject = rules('sylius.order.*', 'sylius.payment.complete');

        subject.toggleIdentifiers(extras);

        expect(stored(subject)).toEqual(['sylius.order.index', 'sylius.order.show']);
    });

    it('does nothing at all when the set is empty', () => {
        const subject = rules('sylius.order.*');

        subject.toggleIdentifiers([]);

        expect(stored(subject)).toEqual(['sylius.order.*']);
    });
});

describe('a column', () => {
    it('acts only on the rows that have the operation', () => {
        const subject = rules();

        subject.toggleColumn(['sylius.order', 'sylius.payment', 'sylius.product'], 'show');

        expect(stored(subject)).toEqual(['sylius.order.show']);
    });
});

describe('a whole row or section', () => {
    it('grants a row as one rule rather than as its operations', () => {
        const subject = rules();

        subject.toggleRow('sylius.order');

        expect(stored(subject)).toEqual(['sylius.order.*']);
    });

    it('clears a section out of "everything", keeping the rows outside it', () => {
        const subject = rules('*.*.*');

        subject.toggleGroup(['sylius.order', 'sylius.payment']);

        expect(stored(subject)).toEqual(['sylius.product.*']);
    });
});

describe('the blanket buttons', () => {
    it('stores read-only as one rule per read operation', () => {
        const subject = rules();

        subject.setGlobal('read');

        expect(stored(subject)).toEqual(['*.*.index', '*.*.show', '*.*.view']);
        expect(subject.globalState()).toBe('read');
    });

    it('reports no blanket at all once one is spent', () => {
        const subject = rules('*.*.*');

        subject.toggleCell('sylius.order.cancel');

        expect(subject.globalState()).toBeNull();
    });

    it('reports "none" only when nothing is stored', () => {
        expect(rules().globalState()).toBe('none');
        expect(rules('*.*.*').globalState()).toBe('all');
    });
});

describe('what gets written to the field', () => {
    it('deduplicates and sorts, so saving twice does not churn the value', () => {
        const subject = rules('sylius.order.*', 'sylius.order.*', '*.*.index');

        expect(subject.normalise().toString()).toBe('*.*.index\nsylius.order.*');
    });
});

import { Controller } from '@hotwired/stimulus';
import PermissionRules from '../permission-rules';

/**
 * The permission tree: an editor for the newline-separated patterns in one hidden field.
 *
 * The checkboxes are not bound to the form. What a role stores is a set of rules, and half of
 * them — `sylius.product.*` — have no single checkbox to bind to, so every control edits the
 * rules and the whole grid is repainted from them.
 *
 * Only two attributes carry data: `data-subject` on a row and `data-permission` on a cell.
 * Everything else is Stimulus wiring.
 */
export default class extends Controller {
    static targets = [
        'field', 'global', 'group', 'groupCheck', 'groupCount', 'row', 'cell', 'identifier',
        'stored', 'storedPanel', 'ruleCount', 'permissionCount',
    ];

    static values = {
        identifiers: Array,
        readOperations: Array,
        openGroups: { type: Number, default: 2 },
    };

    connect() {
        this.rules = new PermissionRules(
            this.identifiersValue,
            this.readOperationsValue,
            this.fieldTarget.value.split('\n').map((pattern) => pattern.trim()).filter(Boolean),
        );

        this.groupTargets.forEach((group, index) => {
            group.dataset.open = index < this.openGroupsValue ? 'true' : 'false';
        });

        this.render();
    }

    cell(event) {
        this.rules.toggleCell(event.currentTarget.dataset.permission);
        this.render();
    }

    row(event) {
        this.rules.toggleRow(PermissionRules.subjectOf(event.currentTarget));
        this.render();
    }

    group(event) {
        this.rules.toggleGroup(this.subjectsOf(this.groupOf(event.currentTarget)));
        this.render();
    }

    column(event) {
        event.preventDefault();

        this.rules.toggleColumn(
            this.subjectsOf(this.groupOf(event.currentTarget)),
            event.currentTarget.dataset.operation,
        );
        this.render();
    }

    /**
     * The same idea as a column's "all", for the operations that are each row's own: they are not
     * one operation down the table, but they are still a set worth granting in one go.
     */
    extras(event) {
        event.preventDefault();

        this.rules.toggleIdentifiers(
            [...this.groupOf(event.currentTarget).querySelectorAll('.rbac-extra [data-permission]')]
                .map((box) => box.dataset.permission),
        );
        this.render();
    }

    global(event) {
        event.preventDefault();
        this.rules.setGlobal(event.currentTarget.dataset.blanket);
        this.render();
    }

    collapse(event) {
        event.preventDefault();
        const group = this.groupOf(event.currentTarget);

        group.dataset.open = 'false' === group.dataset.open ? 'true' : 'false';
    }

    /** One switch for every technical detail: the identifiers, and the rules they add up to. */
    identifiers(event) {
        const hidden = !event.currentTarget.checked;

        this.identifierTargets.forEach((element) => element.classList.toggle('d-none', hidden));
        this.storedPanelTarget.classList.toggle('d-none', hidden);
    }

    filter(event) {
        const query = event.currentTarget.value.trim().toLowerCase();

        this.groupTargets.forEach((group) => {
            let visible = 0;

            group.querySelectorAll('[data-subject]').forEach((row) => {
                const matches = '' === query || this.matches(row, query);

                row.classList.toggle('d-none', !matches);
                visible += matches ? 1 : 0;
            });

            group.classList.toggle('d-none', 0 === visible);

            if ('' !== query) {
                group.dataset.open = 'true';
            }
        });
    }

    render() {
        this.rules.normalise();
        this.fieldTarget.value = this.rules.toString();

        const state = this.rules.globalState();
        this.globalTargets.forEach((button) => button.classList.toggle('active', button.dataset.blanket === state));

        this.cellTargets.forEach((cell) => { cell.checked = this.rules.granted(cell.dataset.permission); });

        this.rowTargets.forEach((box) => {
            this.paint(box, this.rules.operationsOf(PermissionRules.subjectOf(box)));
        });

        // Target arrays come back in document order, so a group and its own controls line up.
        this.groupTargets.forEach((group, index) => {
            const identifiers = this.subjectsOf(group).flatMap((subject) => this.rules.operationsOf(subject));
            const granted = this.rules.grantedCount(identifiers);
            const count = this.groupCountTargets[index];

            this.paint(this.groupCheckTargets[index], identifiers);
            count.textContent = `${granted} / ${identifiers.length}`;
            count.classList.toggle('is-complete', granted > 0 && granted === identifiers.length);
            count.classList.toggle('is-partial', granted > 0 && granted < identifiers.length);
        });

        this.storedTarget.replaceChildren(...this.rules.patterns.map((pattern) => {
            const item = document.createElement('li');

            item.textContent = pattern;
            item.classList.toggle('is-rule', pattern.includes('*'));

            return item;
        }));

        this.ruleCountTarget.textContent = String(this.rules.patterns.length);
        this.permissionCountTarget.textContent = String(this.rules.grantedCount(this.identifiersValue));
    }

    /** A checkbox that stands for many permissions: ticked, cleared, or somewhere in between. */
    paint(box, identifiers) {
        const granted = this.rules.grantedCount(identifiers);

        box.checked = granted === identifiers.length;
        box.indeterminate = granted > 0 && granted < identifiers.length;
    }

    groupOf(element) {
        return element.closest('[data-group]');
    }

    subjectsOf(group) {
        return [...group.querySelectorAll('[data-subject]')].map((row) => row.dataset.subject);
    }

    matches(row, query) {
        const subject = row.dataset.subject;
        const label = (row.querySelector('strong')?.textContent ?? '').toLowerCase();

        return label.includes(query) ||
            subject.toLowerCase().includes(query) ||
            this.rules.operationsOf(subject).some((identifier) => identifier.split('.')[2].includes(query));
    }
}

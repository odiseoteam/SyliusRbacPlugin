/**
 * The rules a role stores, and the edits the permission tree makes to them.
 *
 * Deliberately free of the DOM. Everything about what a pattern covers, when a rule broader
 * than one row has to be paid out and when it can stand, lives here; the controller only
 * renders what this says.
 */
export default class PermissionRules {
    static EVERYTHING = '*.*.*';

    /**
     * @param {string[]} identifiers every permission the application knows about
     * @param {string[]} readOperations the operations "read only" means
     * @param {string[]} patterns what the role stores today
     */
    constructor(identifiers, readOperations, patterns = []) {
        this.identifiers = identifiers;
        this.readOperations = readOperations;
        this.patterns = patterns;
        this.subjects = [...new Set(identifiers.map(PermissionRules.keyOf))];
    }

    static keyOf(identifier) {
        return identifier.split('.').slice(0, 2).join('.');
    }

    /** The subject an element belongs to, read off the row it sits in. */
    static subjectOf(element) {
        return element.closest('[data-subject]').dataset.subject;
    }

    static matches(pattern, identifier) {
        const segments = identifier.split('.');

        return pattern.split('.').every((segment, index) => '*' === segment || segment === segments[index]);
    }

    /** Broader than one row: it can cover subjects that do not exist yet. */
    static isBroad(pattern) {
        const [packageName, subject] = pattern.split('.');

        return '*' === packageName || '*' === subject;
    }

    /** Deduplicated and in a stable order, so the stored value does not churn between saves. */
    normalise() {
        this.patterns = [...new Set(this.patterns)].sort();

        return this;
    }

    toString() {
        return this.patterns.join('\n');
    }

    operationsOf(subjectKey) {
        return this.identifiers.filter((identifier) => identifier.startsWith(`${subjectKey}.`));
    }

    granted(identifier) {
        return this.patterns.some((pattern) => PermissionRules.matches(pattern, identifier));
    }

    grantedCount(identifiers) {
        return identifiers.filter((identifier) => this.granted(identifier)).length;
    }

    add(pattern) {
        if (!this.patterns.includes(pattern)) {
            this.patterns.push(pattern);
        }
    }

    drop(pattern) {
        this.patterns = this.patterns.filter((existing) => existing !== pattern);
    }

    /**
     * Turns the rules broader than one row that cover any of `losing` into one rule per row,
     * keeping exactly what is granted today.
     *
     * Only those rules are spent. Granting something on top of a blanket rule is expressible —
     * `*.*.index` plus `sylius.order.cancel` reads "view everything, and cancel orders" — so
     * adding never costs anything. Taking something away is not: "everything except X" cannot be
     * stored, there are no deny rules, so the blanket has to be paid out first. Each row keeps
     * its own `subject.*` and still picks up operations Sylius adds later; only coverage of
     * resources that do not exist yet is lost, which is what the control losing its selection
     * announces.
     *
     * @param {string[]} losing the identifiers about to stop being granted
     */
    materialise(losing) {
        const broad = this.patterns.filter(
            (pattern) => PermissionRules.isBroad(pattern) &&
                losing.some((identifier) => PermissionRules.matches(pattern, identifier)),
        );

        if (0 === broad.length) {
            return;
        }

        const was = this.identifiers.filter((identifier) => this.granted(identifier));
        broad.forEach((pattern) => this.drop(pattern));

        this.subjects.forEach((subjectKey) => {
            const operations = this.operationsOf(subjectKey);
            const dropped = operations.filter((identifier) => was.includes(identifier) && !this.granted(identifier));

            if (0 === dropped.length) {
                return;
            }

            if (operations.every((identifier) => was.includes(identifier))) {
                this.add(`${subjectKey}.*`);
            } else {
                dropped.forEach((identifier) => this.add(identifier));
            }
        });
    }

    /** Every operation of a row ticked one by one collapses back into the row's rule. */
    collapse(subjectKey) {
        const operations = this.operationsOf(subjectKey);

        if (operations.every((identifier) => this.patterns.includes(identifier))) {
            operations.forEach((identifier) => this.drop(identifier));
            this.add(`${subjectKey}.*`);
        }
    }

    collapseAll() {
        this.subjects.forEach((subjectKey) => this.collapse(subjectKey));
    }

    toggleCell(identifier) {
        const subjectKey = PermissionRules.keyOf(identifier);
        const rule = `${subjectKey}.*`;

        if (!this.granted(identifier)) {
            this.add(identifier);
            this.collapse(subjectKey);

            return;
        }

        this.materialise([identifier]);

        if (!this.patterns.includes(rule)) {
            this.drop(identifier);

            return;
        }

        // The row was granted wholesale; spend that rule and keep every operation but this one.
        this.drop(rule);
        this.operationsOf(subjectKey).forEach((other) => {
            if (other !== identifier) {
                this.add(other);
            }
        });
    }

    toggleRow(subjectKey) {
        this.setSubjects([subjectKey], !this.operationsOf(subjectKey).every((id) => this.granted(id)));
    }

    /** The section checkbox, with the same meaning as a row's: everything here, or nothing. */
    toggleGroup(subjectKeys) {
        const identifiers = subjectKeys.flatMap((subjectKey) => this.operationsOf(subjectKey));

        this.setSubjects(subjectKeys, !identifiers.every((identifier) => this.granted(identifier)));
    }

    toggleColumn(subjectKeys, operation) {
        const keys = subjectKeys.filter((subjectKey) => this.operationsOf(subjectKey).includes(`${subjectKey}.${operation}`));
        const identifiers = keys.map((subjectKey) => `${subjectKey}.${operation}`);
        const allOn = identifiers.every((identifier) => this.granted(identifier));

        if (allOn) {
            this.materialise(identifiers);
        }

        keys.forEach((subjectKey) => {
            const identifier = `${subjectKey}.${operation}`;
            const rule = `${subjectKey}.*`;

            if (!allOn) {
                if (!this.granted(identifier)) {
                    this.add(identifier);
                }
            } else if (this.patterns.includes(rule)) {
                this.drop(rule);
                this.operationsOf(subjectKey).forEach((other) => {
                    if (other !== identifier) {
                        this.add(other);
                    }
                });
            } else {
                this.drop(identifier);
            }
        });

        this.collapseAll();
    }

    /**
     * The blanket rules. `none` and `all` replace everything; `read` leaves `*.*.index` and
     * `*.*.show` standing, which extra ticks then build on rather than spend.
     *
     * @param {'all'|'read'|'none'} what
     */
    setGlobal(what) {
        if ('all' === what) {
            this.patterns = [PermissionRules.EVERYTHING];
        } else if ('read' === what) {
            this.patterns = this.readOperations.map((operation) => `*.*.${operation}`);
        } else {
            this.patterns = [];
        }
    }

    /** Which blanket rules are literally stored, if any — extra grants on top do not clear it. */
    globalState() {
        if (this.patterns.includes(PermissionRules.EVERYTHING)) {
            return 'all';
        }

        if (this.readOperations.every((operation) => this.patterns.includes(`*.*.${operation}`))) {
            return 'read';
        }

        return 0 === this.patterns.length ? 'none' : null;
    }

    /** Grants or clears whole subjects, paying out any broad rule the clearing would contradict. */
    setSubjects(subjectKeys, grant) {
        if (!grant) {
            this.materialise(subjectKeys.flatMap((subjectKey) => this.operationsOf(subjectKey)));
        }

        subjectKeys.forEach((subjectKey) => {
            this.operationsOf(subjectKey).forEach((identifier) => this.drop(identifier));
            this.drop(`${subjectKey}.*`);

            if (grant) {
                this.add(`${subjectKey}.*`);
            }
        });
    }
}

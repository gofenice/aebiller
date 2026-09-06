import Alpine from 'alpinejs';

/**
 * Shared product typeahead used by the stock entry and adjustment line items.
 */
function productPicker(lookupUrl) {
    return {
        lookupUrl,
        results: [],
        searching: false,
        openRow: null,
        timer: null,

        search(index, term) {
            this.openRow = index;
            clearTimeout(this.timer);

            if (!term || term.length < 2) {
                this.results = [];
                return;
            }

            this.searching = true;
            this.timer = setTimeout(async () => {
                try {
                    const response = await fetch(`${this.lookupUrl}?q=${encodeURIComponent(term)}`, {
                        headers: { Accept: 'application/json' },
                    });
                    this.results = response.ok ? await response.json() : [];
                } catch (error) {
                    this.results = [];
                } finally {
                    this.searching = false;
                }
            }, 200);
        },

        closePicker() {
            this.openRow = null;
            this.results = [];
        },
    };
}

const money = (value) => Number(value || 0).toFixed(2);

Alpine.data('stockEntryForm', (config = {}) => ({
    ...productPicker(config.lookupUrl),

    rows: [],
    otherCharges: Number(config.otherCharges || 0),

    init() {
        const existing = config.rows || [];
        this.rows = existing.length ? existing.map((row) => this.makeRow(row)) : [this.makeRow()];
        this.ensureBlankRow();
    },

    makeRow(values = {}) {
        return {
            product: values.product || null,
            product_id: values.product_id || '',
            term: values.term || '',
            quantity: values.quantity ?? '',
            free_quantity: values.free_quantity ?? '',
            unit_cost: values.unit_cost ?? '',
            discount_percent: values.discount_percent ?? '',
            tax_percent: values.tax_percent ?? '',
            selling_price: values.selling_price ?? '',
            batch_number: values.batch_number ?? '',
            manufactured_on: values.manufactured_on ?? '',
            expires_on: values.expires_on ?? '',
        };
    },

    choose(index, product) {
        const row = this.rows[index];
        row.product = product;
        row.product_id = product.id;
        row.term = product.name;
        row.unit_cost = product.cost_price || '';
        row.tax_percent = product.tax_rate ?? '';
        row.selling_price = product.selling_price || '';
        if (row.quantity === '') {
            row.quantity = 1;
        }
        this.closePicker();
        this.ensureBlankRow();
        this.$nextTick(() => document.querySelector(`[data-qty="${index}"]`)?.focus());
    },

    clearRow(index) {
        const row = this.rows[index];
        row.product = null;
        row.product_id = '';
        row.term = '';
    },

    addRow() {
        this.rows.push(this.makeRow());
    },

    removeRow(index) {
        this.rows.splice(index, 1);
        if (this.rows.length === 0) {
            this.addRow();
        }
    },

    ensureBlankRow() {
        const last = this.rows[this.rows.length - 1];
        if (last && last.product_id) {
            this.addRow();
        }
    },

    lineNet(row) {
        const gross = Number(row.quantity || 0) * Number(row.unit_cost || 0);
        return gross - (gross * Number(row.discount_percent || 0)) / 100;
    },

    lineTax(row) {
        return (this.lineNet(row) * Number(row.tax_percent || 0)) / 100;
    },

    lineTotal(row) {
        return this.lineNet(row) + this.lineTax(row);
    },

    lineDisplay(row) {
        return money(this.lineTotal(row));
    },

    get filledRows() {
        return this.rows.filter((row) => row.product_id);
    },

    get subtotal() {
        return this.filledRows.reduce((sum, row) => sum + this.lineNet(row), 0);
    },

    get discountTotal() {
        return this.filledRows.reduce((sum, row) => {
            const gross = Number(row.quantity || 0) * Number(row.unit_cost || 0);
            return sum + (gross * Number(row.discount_percent || 0)) / 100;
        }, 0);
    },

    get taxTotal() {
        return this.filledRows.reduce((sum, row) => sum + this.lineTax(row), 0);
    },

    get grandTotal() {
        return this.subtotal + this.taxTotal + Number(this.otherCharges || 0);
    },

    get totalUnits() {
        return this.filledRows.reduce(
            (sum, row) => sum + Number(row.quantity || 0) + Number(row.free_quantity || 0),
            0,
        );
    },

    display(value) {
        return money(value);
    },
}));

Alpine.data('stockAdjustmentForm', (config = {}) => ({
    ...productPicker(config.lookupUrl),

    reason: config.reason || 'damage',
    rows: [],

    init() {
        const existing = config.rows || [];
        this.rows = existing.length ? existing.map((row) => this.makeRow(row)) : [this.makeRow()];
        this.ensureBlankRow();
    },

    makeRow(values = {}) {
        return {
            product: values.product || null,
            product_id: values.product_id || '',
            term: values.term || '',
            direction: values.direction || 'out',
            quantity: values.quantity ?? '',
            unit_cost: values.unit_cost ?? '',
            notes: values.notes ?? '',
        };
    },

    get isCount() {
        return this.reason === 'stock_count';
    },

    choose(index, product) {
        const row = this.rows[index];
        row.product = product;
        row.product_id = product.id;
        row.term = product.name;
        row.unit_cost = product.cost_price || '';
        this.closePicker();
        this.ensureBlankRow();
        this.$nextTick(() => document.querySelector(`[data-qty="${index}"]`)?.focus());
    },

    clearRow(index) {
        const row = this.rows[index];
        row.product = null;
        row.product_id = '';
        row.term = '';
    },

    addRow() {
        this.rows.push(this.makeRow());
    },

    removeRow(index) {
        this.rows.splice(index, 1);
        if (this.rows.length === 0) {
            this.addRow();
        }
    },

    ensureBlankRow() {
        const last = this.rows[this.rows.length - 1];
        if (last && last.product_id) {
            this.addRow();
        }
    },

    /** Resulting stock for a line, so the user sees the effect before posting. */
    resultingStock(row) {
        if (!row.product) {
            return null;
        }

        const current = Number(row.product.current_stock || 0);

        if (this.isCount) {
            return row.quantity === '' ? current : Number(row.quantity);
        }

        const change = Number(row.quantity || 0);

        return row.direction === 'in' ? current + change : current - change;
    },

    lineValue(row) {
        if (this.isCount) {
            const difference = Math.abs(this.resultingStock(row) - Number(row.product?.current_stock || 0));
            return difference * Number(row.unit_cost || 0);
        }

        return Number(row.quantity || 0) * Number(row.unit_cost || 0);
    },

    get filledRows() {
        return this.rows.filter((row) => row.product_id);
    },

    get totalValue() {
        return this.filledRows.reduce((sum, row) => sum + this.lineValue(row), 0);
    },

    display(value) {
        return money(value);
    },
}));

const round2 = (value) => Math.round((Number(value) + Number.EPSILON) * 100) / 100;

/**
 * The till. Mirrors the server-side pricing in BillingService so the figure on
 * screen is exactly the figure that gets posted.
 */
Alpine.data('posTerminal', (config = {}) => ({
    scanUrl: config.scanUrl,
    cart: [],
    code: '',
    results: [],
    highlight: -1,
    requestId: 0,
    searching: false,
    notice: null,
    billDiscount: '',
    paymentMethod: 'cash',
    amountPaid: '',
    customerName: '',
    customerPhone: '',
    customerVat: '',
    showCustomer: false,

    init() {
        this.focusScanner();
    },

    focusScanner() {
        this.$nextTick(() => this.$refs.scanner?.focus());
    },

    flash(message, tone = 'error') {
        this.notice = { message, tone };
        setTimeout(() => { this.notice = null; }, 3500);
    },

    /**
     * Live suggestions while the cashier types. Nothing is added to the basket
     * from here — a partial code must never pick an item on its own.
     */
    suggest() {
        this.lookup(false);
    },

    /**
     * Enter: from the keyboard, or sent by the barcode scanner after the code.
     */
    submitCode() {
        // Only an arrow-key choice counts as a deliberate pick; otherwise the
        // full code that was just typed is looked up fresh.
        if (this.highlight >= 0 && this.results[this.highlight]) {
            this.addProduct(this.results[this.highlight]);
            return;
        }

        this.lookup(true);
    },

    /**
     * @param {boolean} addExactMatch add the item when the code resolves to one product
     */
    async lookup(addExactMatch) {
        const code = this.code.trim();
        const request = ++this.requestId;

        if (code.length < 2) {
            this.results = [];
            this.highlight = -1;
            return;
        }

        this.searching = true;

        try {
            const response = await fetch(`${this.scanUrl}?code=${encodeURIComponent(code)}`, {
                headers: { Accept: 'application/json' },
            });
            const payload = response.ok ? await response.json() : { match: null, results: [] };

            // A newer keystroke has already fired; drop this stale answer.
            if (request !== this.requestId) {
                return;
            }

            this.results = payload.results ?? [];
            this.highlight = -1;

            if (!addExactMatch) {
                return;
            }

            if (payload.match) {
                this.addProduct(payload.match);
            } else if (this.results.length === 0) {
                this.flash(`Nothing found for "${code}".`);
            }
        } catch (error) {
            if (request === this.requestId) {
                this.flash('Could not reach the product lookup.');
            }
        } finally {
            if (request === this.requestId) {
                this.searching = false;
            }
        }
    },

    moveHighlight(step) {
        if (this.results.length === 0) {
            return;
        }

        const next = this.highlight + step;
        this.highlight = Math.min(Math.max(next, 0), this.results.length - 1);
    },

    dismissSuggestions() {
        this.results = [];
        this.highlight = -1;
    },

    addProduct(product) {
        if (product.current_stock <= 0) {
            this.flash(`${product.name} is out of stock.`);
            return;
        }

        this.code = '';
        this.dismissSuggestions();
        const line = this.cart.find((item) => item.id === product.id);

        if (line) {
            if (line.quantity + 1 > product.current_stock) {
                this.flash(`Only ${product.current_stock} ${product.unit ?? ''} of ${product.name} left.`);
                return;
            }
            line.quantity = round2(line.quantity + 1);
            this.focusScanner();
            return;
        }

        this.cart.push({
            id: product.id,
            name: product.name,
            sku: product.sku,
            unit: product.unit,
            is_weighable: product.is_weighable,
            current_stock: product.current_stock,
            vat_rate: product.vat_rate,
            price_includes_tax: product.price_includes_tax,
            unit_price: product.unit_price,
            quantity: product.is_weighable ? (product.min_sale_quantity || 1) : 1,
            discount_percent: '',
        });

        this.dismissSuggestions();

        // A weighed item needs the scale reading typed in, so go straight there.
        if (product.is_weighable) {
            const index = this.cart.length - 1;
            this.$nextTick(() => {
                const input = document.querySelector(`[data-cart-qty="${index}"]`);
                input?.focus();
                input?.select();
            });
        } else {
            this.focusScanner();
        }
    },

    changeQuantity(index, delta) {
        const line = this.cart[index];
        const next = round2(Number(line.quantity || 0) + delta);

        if (next <= 0) {
            this.removeLine(index);
            return;
        }

        line.quantity = next;
    },

    removeLine(index) {
        this.cart.splice(index, 1);
        this.focusScanner();
    },

    clearCart() {
        this.cart = [];
        this.billDiscount = '';
        this.amountPaid = '';
        this.customerName = '';
        this.customerPhone = '';
        this.customerVat = '';
        this.dismissSuggestions();
        this.focusScanner();
    },

    lineGross(line) {
        return round2(Number(line.quantity || 0) * Number(line.unit_price || 0));
    },

    lineDiscount(line) {
        return round2(this.lineGross(line) * Number(line.discount_percent || 0) / 100);
    },

    lineNet(line) {
        return round2(this.lineGross(line) - this.lineDiscount(line));
    },

    overStock(line) {
        return Number(line.quantity || 0) > Number(line.current_stock || 0);
    },

    get itemsGross() {
        return round2(this.cart.reduce((sum, line) => sum + this.lineGross(line), 0));
    },

    get lineDiscountTotal() {
        return round2(this.cart.reduce((sum, line) => sum + this.lineDiscount(line), 0));
    },

    get afterLineDiscount() {
        return round2(this.cart.reduce((sum, line) => sum + this.lineNet(line), 0));
    },

    get appliedBillDiscount() {
        return round2(Math.min(Math.max(Number(this.billDiscount || 0), 0), this.afterLineDiscount));
    },

    /** VAT split per line, with the bill discount spread pro-rata. */
    get breakdown() {
        const base = this.afterLineDiscount;
        const discount = this.appliedBillDiscount;
        let distributed = 0;
        let subtotal = 0;
        let vat = 0;
        let total = 0;

        this.cart.forEach((line, index) => {
            const share = index === this.cart.length - 1
                ? round2(discount - distributed)
                : (base > 0 ? round2(discount * this.lineNet(line) / base) : 0);

            distributed = round2(distributed + share);

            const net = round2(this.lineNet(line) - share);
            const rate = Number(line.vat_rate || 0);
            const lineVat = line.price_includes_tax
                ? round2(net * rate / (100 + rate))
                : round2(net * rate / 100);

            subtotal = round2(subtotal + (line.price_includes_tax ? round2(net - lineVat) : net));
            vat = round2(vat + lineVat);
            total = round2(total + (line.price_includes_tax ? net : round2(net + lineVat)));
        });

        return { subtotal, vat, total };
    },

    get grandTotal() {
        return this.breakdown.total;
    },

    get totalUnits() {
        return round2(this.cart.reduce((sum, line) => sum + Number(line.quantity || 0), 0));
    },

    get needsTendering() {
        return this.paymentMethod === 'cash';
    },

    get tendered() {
        return Number(this.amountPaid || 0);
    },

    get changeDue() {
        if (!this.needsTendering) {
            return 0;
        }

        return round2(Math.max(this.tendered - this.grandTotal, 0));
    },

    get shortfall() {
        if (!this.needsTendering || this.amountPaid === '') {
            return 0;
        }

        return round2(Math.max(this.grandTotal - this.tendered, 0));
    },

    tender(amount) {
        this.amountPaid = amount === 'exact' ? this.grandTotal.toFixed(2) : String(amount);
    },

    get blockers() {
        if (this.cart.length === 0) {
            return 'Scan a product to start the bill.';
        }

        const short = this.cart.find((line) => this.overStock(line));

        if (short) {
            return `${short.name} has only ${short.current_stock} ${short.unit ?? ''} in stock.`;
        }

        if (this.needsTendering && this.amountPaid !== '' && this.shortfall > 0) {
            return `Cash tendered is short by ${this.shortfall.toFixed(2)}.`;
        }

        return null;
    },

    get canComplete() {
        return this.blockers === null;
    },

    money(value) {
        return Number(value || 0).toFixed(2);
    },
}));

/**
 * The product form. Keeps the VAT-exclusive and VAT-inclusive selling prices
 * tied together: type either one and the other is worked out from the VAT rate.
 */
Alpine.data('productForm', (config = {}) => ({
    type: config.type || 'packaged',
    cost: Number(config.cost) || 0,
    taxRate: Number(config.taxRate) || 0,
    priceExcl: 0,
    priceIncl: 0,
    lastEdited: 'incl',
    trackBatches: Boolean(config.trackBatches),
    trackExpiry: Boolean(config.trackExpiry),
    openingStock: Number(config.openingStock) || 0,

    init() {
        // The shelf price is what is stored; the net figure hangs off it.
        this.priceIncl = round2(Number(config.sellingPrice) || 0);
        this.priceExcl = this.stripTax(this.priceIncl);
    },

    get factor() {
        return 1 + this.taxRate / 100;
    },

    addTax(value) {
        return round2(Number(value || 0) * this.factor);
    },

    stripTax(value) {
        return round2(Number(value || 0) / this.factor);
    },

    /** Typed into the excluding-VAT box. */
    fromExclusive() {
        this.lastEdited = 'excl';
        this.priceIncl = this.addTax(this.priceExcl);
    },

    /** Typed into the including-VAT box. */
    fromInclusive() {
        this.lastEdited = 'incl';
        this.priceExcl = this.stripTax(this.priceIncl);
    },

    /**
     * Changing the rate keeps the figure that was typed last and re-derives
     * the other one, so the number the user chose is never overwritten.
     */
    onRateChange() {
        this.lastEdited === 'excl' ? this.fromExclusive() : this.fromInclusive();
    },

    get vatAmount() {
        return round2(this.priceIncl - this.priceExcl);
    },

    get isLoose() {
        return this.type === 'loose';
    },

    /** Margin is measured on the net price, never on the VAT. */
    get margin() {
        if (!this.cost || !this.priceExcl) {
            return null;
        }

        return (((this.priceExcl - this.cost) / this.cost) * 100).toFixed(1);
    },

    get profit() {
        return (this.priceExcl - this.cost).toFixed(2);
    },

    get openingValue() {
        return (this.openingStock * this.cost).toFixed(2);
    },

    generateBarcode() {
        const body = '2' + String(Math.floor(Math.random() * 1e11)).padStart(11, '0');
        let sum = 0;

        for (let i = 0; i < 12; i++) {
            sum += Number(body[i]) * (i % 2 === 0 ? 1 : 3);
        }

        this.$refs.barcode.value = body + ((10 - (sum % 10)) % 10);
    },

    money(value) {
        return Number(value || 0).toFixed(2);
    },
}));

window.Alpine = Alpine;

Alpine.start();

/* ==========================================================================
   Live dashboard updates — spec section 5.6
   --------------------------------------------------------------------------
   "As new orders come in on the creator's code, the summary figures and the
   orders list should update on their own — within a few seconds, without the
   creator refreshing the page."

   Polling rather than websockets, by decision during spec review: it meets the
   "within a few seconds" requirement, needs no long-running process, and runs
   on ordinary PHP hosting. If this is ever swapped for Reverb, only this file
   and the /live endpoint need to change.
   ========================================================================== */

(function () {
    'use strict';

    var root = document.querySelector('[data-live-url]');
    if (!root) return;

    var url = root.getAttribute('data-live-url');
    var intervalSeconds = parseInt(root.getAttribute('data-poll-seconds'), 10) || 5;

    var summaryBody = document.querySelector('[data-summary-body]');
    var ordersBody = document.querySelector('[data-orders-body]');
    var payoutValue = document.querySelector('[data-payout-value]');
    var unitsValue = document.querySelector('[data-units-value]');
    var refundedValue = document.querySelector('[data-refunded-value]');
    var nudgeText = document.querySelector('[data-nudge-text]');
    var statusBar = document.querySelector('[data-livebar]');
    var statusText = document.querySelector('[data-livebar-text]');
    var totalCount = document.querySelector('[data-total-orders]');

    var timer = null;
    var failures = 0;

    /* Order ids already on the page, so a poll can tell which rows are new and
       flash only those. Seeded from the server-rendered markup. */
    var seen = new Set();
    if (ordersBody) {
        Array.prototype.forEach.call(ordersBody.querySelectorAll('tr[data-order-id]'), function (tr) {
            seen.add(tr.getAttribute('data-order-id'));
        });
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function setStatus(state, text) {
        if (!statusBar) return;
        statusBar.classList.remove('is-stale', 'is-offline');
        if (state !== 'ok') statusBar.classList.add(state === 'stale' ? 'is-stale' : 'is-offline');
        if (statusText) statusText.textContent = text;
    }

    function renderSummary(summary) {
        if (!summaryBody || !summary || !summary.rows) return;

        summaryBody.innerHTML = summary.rows.map(function (row) {
            var cls = row.emphasis ? 'is-total' : (row.muted ? 'is-muted' : '');
            return '<tr class="' + cls + '">' +
                   '<td>' + escapeHtml(row.label) + '</td>' +
                   '<td class="tabular">' + escapeHtml(row.value) + '</td>' +
                   '</tr>';
        }).join('');

        if (payoutValue && summary.rows.length) {
            payoutValue.textContent = summary.rows[summary.rows.length - 1].value;
        }
        if (unitsValue) unitsValue.textContent = summary.units_sold;
        if (refundedValue) refundedValue.textContent = summary.refunded_orders;
    }

    function renderOrders(rows) {
        if (!ordersBody || !rows) return;

        var fresh = [];

        ordersBody.innerHTML = rows.map(function (row) {
            var id = String(row.order_id) + '|' + String(row.placed_at_iso);
            var isNew = !seen.has(id);
            if (isNew) fresh.push(id);

            var classes = [];
            if (row.is_refunded) classes.push('is-refunded');
            /* Only flash on a later poll — not on the first one after load,
               which would light up the whole table for no reason. */
            if (isNew && seen.size > 0) classes.push('is-new');

            return '<tr data-order-id="' + escapeHtml(id) + '"' +
                   (classes.length ? ' class="' + classes.join(' ') + '"' : '') + '>' +
                   '<td class="muted">' + escapeHtml(row.serial) + '</td>' +
                   '<td class="tabular">' + escapeHtml(row.order_id) + '</td>' +
                   '<td class="tabular">' + escapeHtml(row.placed_at) + '</td>' +
                   '<td>' + escapeHtml(row.name) + '</td>' +
                   '<td><span class="code">' + escapeHtml(row.code) + '</span></td>' +
                   '<td>' + escapeHtml(row.country) + '</td>' +
                   '<td>' + escapeHtml(row.state) + '</td>' +
                   '<td class="plugin">' + escapeHtml(row.plugin) + '</td>' +
                   '<td>' + escapeHtml(row.currency) + '</td>' +
                   '<td class="num">' + escapeHtml(row.amount) +
                   (row.is_refunded ? ' <span class="badge badge--refunded">Refunded</span>' : '') +
                   '</td>' +
                   '</tr>';
        }).join('');

        fresh.forEach(function (id) { seen.add(id); });
    }

    function poll() {
        fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (response) {
                /* The idle timeout signed us out mid-poll (spec section 3).
                   Send the creator to the sign-in page rather than leaving a
                   dead page quietly showing stale money. */
                if (response.status === 401) {
                    return response.json().then(function (body) {
                        window.location.href = body.redirect || '/login';
                        return null;
                    });
                }

                if (!response.ok) throw new Error('HTTP ' + response.status);

                return response.json();
            })
            .then(function (data) {
                if (!data) return;

                failures = 0;

                if (data.live === false) {
                    stop();
                    setStatus('offline', data.reason || 'This sale has ended.');
                    return;
                }

                renderSummary(data.summary);
                renderOrders(data.rows);

                if (nudgeText && data.encouragement) nudgeText.textContent = data.encouragement;
                if (totalCount && typeof data.total_orders !== 'undefined') {
                    totalCount.textContent = data.total_orders;
                }

                setStatus('ok', 'Updating live');
            })
            .catch(function () {
                failures += 1;
                /* One dropped request is a blip on mobile data, not a problem
                   worth shouting about. Several in a row is worth saying. */
                if (failures >= 3) {
                    setStatus('stale', 'Reconnecting…');
                }
            });
    }

    function start() {
        if (timer) return;
        timer = window.setInterval(poll, intervalSeconds * 1000);
    }

    function stop() {
        if (!timer) return;
        window.clearInterval(timer);
        timer = null;
    }

    /* A dashboard left open in a background tab should not keep hitting the
       server. Polling resumes, with an immediate refresh, when it is looked at
       again. */
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stop();
        } else {
            poll();
            start();
        }
    });

    start();
})();

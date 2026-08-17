/* ==========================================================================
   Live updating for the internal overview.
   --------------------------------------------------------------------------
   Same approach as the creator dashboard (see dashboard.js): poll a JSON
   endpoint every few seconds so the cross-creator totals move during a running
   sale. Kept separate because the shape of the payload is different — rows are
   creators here, not orders.
   ========================================================================== */

(function () {
    'use strict';

    var root = document.querySelector('[data-live-url]');
    if (!root) return;

    var url = root.getAttribute('data-live-url');
    var intervalSeconds = parseInt(root.getAttribute('data-poll-seconds'), 10) || 5;

    var body = document.querySelector('[data-overview-body]');
    var statusBar = document.querySelector('[data-livebar]');
    var statusText = document.querySelector('[data-livebar-text]');
    var totalCreators = document.querySelector('[data-total-creators]');
    var totalUnits = document.querySelector('[data-total-units]');

    var timer = null;
    var failures = 0;

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

    function badgeFor(status) {
        var map = {
            'Paid': 'paid',
            'Invoice uploaded': 'invoiced',
            'Live': 'live',
            'Awaiting your invoice': 'awaiting',
            'Awaiting close-out': 'awaiting'
        };
        return map[status] || 'awaiting';
    }

    function render(rows) {
        if (!body || !rows) return;

        body.innerHTML = rows.map(function (row) {
            var codes = (row.codes || []).map(function (c) {
                return '<span class="code">' + escapeHtml(c) + '</span>';
            }).join('');

            var rate = String(row.rate).replace(/\.?0+$/, '');

            return '<tr>' +
                '<td><div class="who__name">' + escapeHtml(row.name) + '</div>' +
                '<div class="who__meta">' + escapeHtml(row.email) + '</div></td>' +
                '<td><div class="codes">' + codes + '</div></td>' +
                '<td class="num">' + escapeHtml(row.units) + '</td>' +
                '<td class="num">' + (row.refunded > 0
                    ? '<span class="badge badge--refunded">' + escapeHtml(row.refunded) + '</span>'
                    : '<span class="muted">0</span>') + '</td>' +
                '<td class="num">' + escapeHtml(rate) + '%</td>' +
                '<td class="num">' + escapeHtml(row.gross) + '</td>' +
                '<td class="num"><strong>' + escapeHtml(row.payout) + '</strong></td>' +
                '<td><span class="badge badge--' + badgeFor(row.status) + '">' +
                escapeHtml(row.status) + '</span></td>' +
                '<td class="shrink"></td>' +
                '</tr>';
        }).join('');
    }

    function poll() {
        fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (response.status === 401) {
                    return response.json().then(function (b) {
                        window.location.href = b.redirect || '/login';
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

                render(data.rows);
                if (totalCreators) totalCreators.textContent = data.creators;
                if (totalUnits) totalUnits.textContent = data.units;
                setStatus('ok', 'Updating live');
            })
            .catch(function () {
                failures += 1;
                if (failures >= 3) setStatus('stale', 'Reconnecting…');
            });
    }

    function start() { if (!timer) timer = window.setInterval(poll, intervalSeconds * 1000); }
    function stop() { if (timer) { window.clearInterval(timer); timer = null; } }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) { stop(); } else { poll(); start(); }
    });

    start();
})();

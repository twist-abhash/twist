(function () {
    const app = document.getElementById('auctionApp');
    if (!app) {
        return;
    }

    const auctionId = app.dataset.auctionId;
    const csrfToken = app.dataset.csrfToken;

    const statusEl = document.getElementById('auctionStatus');
    const currentPriceEl = document.getElementById('currentPrice');
    const minNextBidEl = document.getElementById('minNextBid');
    const countdownEl = document.getElementById('countdown');
    const bidHistoryBody = document.getElementById('bidHistoryBody');
    const bidForm = document.getElementById('bidForm');
    const bidAmountInput = document.getElementById('bidAmount');
    const bidMessages = document.getElementById('bidMessages');

    let latestEndTime = countdownEl ? countdownEl.dataset.endTime : null;

    function money(v) {
        return Number(v).toFixed(2);
    }

    function renderMessage(type, text) {
        if (!bidMessages) return;
        bidMessages.innerHTML = '<div class="alert ' + (type === 'success' ? 'alert-success' : 'alert-error') + '">' + text + '</div>';
    }

    function setBidFormEnabled(enabled) {
        if (!bidForm) return;
        const controls = bidForm.querySelectorAll('input,button');
        controls.forEach(function (el) {
            el.disabled = !enabled;
        });
    }

    function updateCountdown() {
        if (!countdownEl || !latestEndTime) return;

        const end = new Date(latestEndTime.replace(' ', 'T')).getTime();
        const now = Date.now();
        const diff = end - now;

        if (diff <= 0) {
            countdownEl.textContent = 'Ended';
            return;
        }

        const sec = Math.floor(diff / 1000);
        const h = Math.floor(sec / 3600);
        const m = Math.floor((sec % 3600) / 60);
        const s = sec % 60;
        countdownEl.textContent = h + 'h ' + m + 'm ' + s + 's';
    }

    async function fetchState() {
        try {
            const response = await fetch('/api/get_auction_state.php?auction_id=' + encodeURIComponent(auctionId), {
                credentials: 'same-origin'
            });
            const data = await response.json();

            if (!data.success) {
                renderMessage('error', data.message || 'State update failed.');
                return;
            }

            if (statusEl) statusEl.textContent = data.status;
            if (currentPriceEl) currentPriceEl.textContent = 'NPR ' + money(data.current_price);
            if (minNextBidEl) minNextBidEl.textContent = 'NPR ' + money(data.min_next_bid);

            latestEndTime = data.end_time;

            if (bidAmountInput) {
                bidAmountInput.min = money(data.min_next_bid);
                bidAmountInput.placeholder = 'Minimum NPR ' + money(data.min_next_bid);
            }

            const live = data.status === 'Live';
            setBidFormEnabled(live);
        } catch (err) {
            renderMessage('error', 'Could not refresh auction state.');
        }
    }

    async function fetchHistory() {
        try {
            const response = await fetch('/api/get_bid_history.php?auction_id=' + encodeURIComponent(auctionId), {
                credentials: 'same-origin'
            });
            const data = await response.json();

            if (!data.success || !Array.isArray(data.history)) {
                return;
            }

            if (data.history.length === 0) {
                bidHistoryBody.innerHTML = '<tr><td colspan="3">No bids yet.</td></tr>';
                return;
            }

            bidHistoryBody.innerHTML = data.history.map(function (item) {
                return '<tr>' +
                    '<td>' + item.bidder_name + '</td>' +
                    '<td>NPR ' + money(item.bid_amount) + '</td>' +
                    '<td>' + item.created_at + '</td>' +
                    '</tr>';
            }).join('');
        } catch (err) {
            // Quietly ignore and keep old list.
        }
    }

    if (bidForm) {
        bidForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            const amount = bidAmountInput ? bidAmountInput.value : '';

            try {
                const body = new URLSearchParams();
                body.set('csrf_token', csrfToken);
                body.set('auction_id', auctionId);
                body.set('bid_amount', amount);

                const response = await fetch('/api/place_bid.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    credentials: 'same-origin',
                    body: body.toString()
                });

                const data = await response.json();
                if (!data.success) {
                    renderMessage('error', data.message || 'Bid failed.');
                    return;
                }

                renderMessage('success', data.message || 'Bid placed.');
                bidAmountInput.value = '';
                await fetchState();
                await fetchHistory();
            } catch (err) {
                renderMessage('error', 'Could not place bid right now.');
            }
        });
    }

    fetchState();
    fetchHistory();
    updateCountdown();

    setInterval(function () {
        fetchState();
        fetchHistory();
    }, 2000);

    setInterval(updateCountdown, 1000);
})();

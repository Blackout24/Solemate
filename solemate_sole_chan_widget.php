<?php
/**
 * Sole-chan site-wide chat widget.
 * Include this file once, right before </body>, on every page:
 *   <?php include 'solemate_sole_chan_widget.php'; ?>
 *
 * Talks to solemate_sole_chan_api.php via fetch().
 * No page-specific setup required — it's fully self-contained.
 */
?>
<style>
#solechan-launcher {
    position: fixed;
    bottom: 24px;
    right: 24px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #1a1a2e;
    color: #fff;
    border: none;
    cursor: pointer;
    z-index: 9999;
    box-shadow: 0 4px 14px rgba(0,0,0,0.25);
    font-size: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.15s ease;
}
#solechan-launcher:hover { transform: scale(1.08); }

#solechan-window {
    position: fixed;
    bottom: 96px;
    right: 24px;
    width: 340px;
    max-width: 90vw;
    height: 460px;
    max-height: 70vh;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.25);
    display: none;
    flex-direction: column;
    overflow: hidden;
    z-index: 9999;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}
#solechan-window.open { display: flex; }

#solechan-header {
    background: #1a1a2e;
    color: #fff;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
#solechan-header .title { font-weight: 600; font-size: 15px; }
#solechan-header .subtitle { font-size: 11px; opacity: 0.7; margin-top: 2px; }
#solechan-close {
    background: none;
    border: none;
    color: #fff;
    font-size: 18px;
    cursor: pointer;
    opacity: 0.8;
}
#solechan-close:hover { opacity: 1; }

#solechan-messages {
    flex: 1;
    overflow-y: auto;
    padding: 14px;
    background: #f7f7fb;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.solechan-msg {
    max-width: 80%;
    padding: 9px 12px;
    border-radius: 12px;
    font-size: 13.5px;
    line-height: 1.4;
    white-space: pre-wrap;
}
.solechan-msg.bot {
    background: #fff;
    border: 1px solid #e5e5ee;
    align-self: flex-start;
    border-bottom-left-radius: 3px;
}
.solechan-msg.user {
    background: #1a1a2e;
    color: #fff;
    align-self: flex-end;
    border-bottom-right-radius: 3px;
}
.solechan-msg.typing {
    background: #fff;
    border: 1px solid #e5e5ee;
    align-self: flex-start;
    color: #999;
    font-style: italic;
}

#solechan-inputbar {
    display: flex;
    border-top: 1px solid #e5e5ee;
    padding: 8px;
    gap: 6px;
}
#solechan-input {
    flex: 1;
    border: 1px solid #ddd;
    border-radius: 20px;
    padding: 9px 14px;
    font-size: 13.5px;
    outline: none;
}
#solechan-input:focus { border-color: #1a1a2e; }
#solechan-send {
    background: #1a1a2e;
    color: #fff;
    border: none;
    border-radius: 20px;
    padding: 0 16px;
    font-size: 13px;
    cursor: pointer;
}
#solechan-send:disabled { opacity: 0.5; cursor: default; }
</style>

<button id="solechan-launcher" aria-label="Chat with Sole-chan">👟</button>

<div id="solechan-window">
    <div id="solechan-header">
        <div>
            <div class="title">Sole-chan</div>
            <div class="subtitle">SoleMate support</div>
        </div>
        <button id="solechan-close" aria-label="Close chat">✕</button>
    </div>
    <div id="solechan-messages"></div>
    <div id="solechan-inputbar">
        <input id="solechan-input" type="text" placeholder="Ask about bookings, orders, pricing..." autocomplete="off" />
        <button id="solechan-send">Send</button>
    </div>
</div>

<script>
(function () {
    var launcher = document.getElementById('solechan-launcher');
    var win = document.getElementById('solechan-window');
    var closeBtn = document.getElementById('solechan-close');
    var messages = document.getElementById('solechan-messages');
    var input = document.getElementById('solechan-input');
    var sendBtn = document.getElementById('solechan-send');

    var API_ENDPOINT = 'solemate_sole_chan_api.php';
    var STORAGE_KEY = 'solechan_history';

    var history = [];
    try {
        var saved = sessionStorage.getItem(STORAGE_KEY);
        if (saved) history = JSON.parse(saved);
    } catch (e) { history = []; }

    function persist() {
        try { sessionStorage.setItem(STORAGE_KEY, JSON.stringify(history)); } catch (e) {}
    }

    function renderMessage(role, text) {
        var el = document.createElement('div');
        el.className = 'solechan-msg ' + (role === 'user' ? 'user' : 'bot');
        el.textContent = text;
        messages.appendChild(el);
        messages.scrollTop = messages.scrollHeight;
        return el;
    }

    function renderHistory() {
        messages.innerHTML = '';
        if (history.length === 0) {
            renderMessage('bot', "Welcome to SoleMate! I'm Sole-chan — ask me about bookings, pricing, order tracking, or custom designs.");
            return;
        }
        history.forEach(function (m) { renderMessage(m.role, m.content); });
    }

    function toggleOpen(open) {
        win.classList.toggle('open', open);
        if (open) {
            renderHistory();
            input.focus();
        }
    }

    launcher.addEventListener('click', function () {
        toggleOpen(!win.classList.contains('open'));
    });
    closeBtn.addEventListener('click', function () { toggleOpen(false); });

    function sendMessage() {
        var text = input.value.trim();
        if (!text) return;

        history.push({ role: 'user', content: text });
        renderMessage('user', text);
        persist();
        input.value = '';
        sendBtn.disabled = true;

        var typingEl = renderMessage('bot', 'Sole-chan is thinking...');
        typingEl.classList.add('typing');

        fetch(API_ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ history: history })
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            typingEl.remove();
            var reply = (data && data.reply) ? data.reply : "Hmm, I couldn't reach the workshop just now — try again in a moment.";
            history.push({ role: 'assistant', content: reply });
            renderMessage('bot', reply);
            persist();
        })
        .catch(function () {
            typingEl.remove();
            renderMessage('bot', "Connection hiccup on my end — mind trying that again?");
        })
        .finally(function () {
            sendBtn.disabled = false;
        });
    }

    sendBtn.addEventListener('click', sendMessage);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') sendMessage();
    });
})();
</script>

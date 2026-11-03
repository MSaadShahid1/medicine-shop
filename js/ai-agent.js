/* Local chat interface for the application assistant. */
(() => {
    'use strict';
    const panel = document.getElementById('wxAgentPanel');
    const openButton = document.querySelector('[data-agent-open]');
    if (!panel || !openButton) return;

    const closeButton = panel.querySelector('[data-agent-close]');
    const newChatButton = panel.querySelector('[data-agent-new-chat]');
    const form = panel.querySelector('[data-agent-form]');
    const input = panel.querySelector('#wxAgentInput');
    const messagesElement = panel.querySelector('[data-agent-messages]');
    const historyKey = 'farms.ai-agent.conversation.v1';
    const maxHistoryMessages = 24;
    const maxContextMessages = 10;
    const maxHistoryMessageLength = 1500;
    const history = readHistory();
    let busy = false;
    let greeted = history.length > 0;
    let closeTimer = null;

    function readHistory() {
        try {
            const stored = JSON.parse(window.sessionStorage.getItem('farms.ai-agent.conversation.v1') || '[]');
            if (!Array.isArray(stored)) return [];
            return stored
                .filter(message => message && ['user', 'assistant'].includes(message.role)
                    && typeof message.content === 'string' && message.content.trim() !== '')
                .slice(-24)
                .map(message => ({ role: message.role, content: message.content.slice(0, 1500) }));
        } catch (_) {
            return [];
        }
    }

    function saveHistory() {
        try {
            window.sessionStorage.setItem(historyKey, JSON.stringify(history.slice(-maxHistoryMessages)));
        } catch (_) {
            // The chat remains available when the browser blocks tab storage.
        }
    }

    function addMessage(role, text) {
        const message = document.createElement('article');
        message.className = `wx-agent-message wx-agent-message-${role}`;
        const label = document.createElement('span');
        label.className = 'wx-agent-message-label';
        label.textContent = role === 'user' ? 'YOU' : 'YOUR CO-PILOT';
        const bubble = document.createElement('p');
        bubble.textContent = text;
        message.append(label, bubble);
        messagesElement.appendChild(message);
        messagesElement.scrollTop = messagesElement.scrollHeight;
        return message;
    }

    history.forEach(message => addMessage(message.role, message.content));

    function recordMessage(role, text) {
        history.push({ role, content: text.slice(0, maxHistoryMessageLength) });
        if (history.length > maxHistoryMessages) {
            history.splice(0, history.length - maxHistoryMessages);
        }
        saveHistory();
        return addMessage(role, text);
    }

    function showGreeting() {
        if (greeted) return;
        greeted = true;
        addMessage('assistant', 'Hi! I’m your Medicine Shop co-pilot. I can help with this website, your shop records, and the form you’re on. What would you like to tackle?');
        showSuggestions();
    }

    function showSuggestions() {
        const suggestions = document.createElement('section');
        suggestions.className = 'wx-agent-suggestions';
        const heading = document.createElement('strong');
        heading.textContent = 'A few things I can help with';
        const prompts = [
            'Show me what I can do on this page',
            'Find a product’s stock and price',
            'Help me add a customer',
        ];
        if (formContext().length > 0) prompts.unshift('Help me fill in this form');
        const choices = document.createElement('div');
        choices.className = 'wx-agent-suggestion-list';
        prompts.forEach(prompt => {
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.agentSuggestion = prompt;
            button.textContent = prompt;
            choices.appendChild(button);
        });
        suggestions.append(heading, choices);
        messagesElement.appendChild(suggestions);
        messagesElement.scrollTop = messagesElement.scrollHeight;
    }

    function formContext() {
        const fields = [];
        document.querySelectorAll('form input[name], form textarea[name], form select[name]').forEach(field => {
            if (!field.getClientRects().length || field.disabled || field.readOnly) return;
            if (field.type && ['hidden', 'password', 'file', 'submit', 'button', 'reset', 'checkbox', 'radio'].includes(field.type)) return;
            const labelElement = field.id ? document.querySelector(`label[for="${CSS.escape(field.id)}"]`) : null;
            const label = labelElement?.textContent?.trim()
                || field.closest('label')?.textContent?.trim()
                || field.getAttribute('aria-label')
                || field.placeholder
                || field.name;
            const item = {
                name: field.name,
                label: label.replace(/\s+/g, ' ').slice(0, 120),
                type: field.tagName === 'TEXTAREA' ? 'textarea'
                    : field.tagName === 'SELECT' ? 'select'
                        : ['text', 'email', 'tel', 'number', 'date'].includes(field.type) ? field.type : 'text',
                required: field.required,
            };
            if (field.tagName === 'SELECT') {
                item.options = Array.from(field.options)
                    .filter(option => !option.disabled && option.value !== '')
                    .slice(0, 40)
                    .map(option => ({ value: option.value.slice(0, 100), label: option.textContent.trim().slice(0, 100) }));
            }
            if (!fields.some(existing => existing.name === item.name)) fields.push(item);
        });
        return fields.slice(0, 40);
    }

    function applyFill(values) {
        let filled = 0;
        Object.entries(values).forEach(([name, value]) => {
            const field = Array.from(document.querySelectorAll('form [name]'))
                .find(candidate => candidate.name === name && candidate.getClientRects().length
                    && !candidate.disabled && !candidate.readOnly
                    && !['hidden', 'password', 'file', 'submit', 'button', 'reset', 'checkbox', 'radio'].includes(candidate.type));
            if (!field || typeof value !== 'string') return;
            if (field.tagName === 'SELECT' && !Array.from(field.options).some(option => option.value === value)) return;
            field.value = value;
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
            filled++;
        });
        return filled;
    }

    function safeLocalUrl(path) {
        if (typeof path !== 'string' || !/^[a-zA-Z0-9_-]+\.php(?:\?[a-zA-Z0-9_=&%-]+)?$/.test(path)) return null;
        const target = new URL(path, window.location.href);
        const base = new URL('.', window.location.href);
        return target.origin === base.origin && target.pathname.startsWith(base.pathname) ? target : null;
    }

    function makeConfirmation(customer, confirmationToken) {
        const card = document.createElement('section');
        card.className = 'wx-agent-confirm';
        const title = document.createElement('strong');
        title.textContent = customer.customer_name;
        card.appendChild(title);
        [['Phone', customer.phone], ['Address', customer.address]].forEach(([label, value]) => {
            if (!value) return;
            const detail = document.createElement('span');
            detail.textContent = `${label}: ${value}`;
            card.appendChild(detail);
        });
        const actions = document.createElement('div');
        actions.className = 'wx-agent-confirm-actions';
        const confirm = document.createElement('button');
        confirm.type = 'button';
        confirm.className = 'wx-agent-confirm-button';
        confirm.textContent = 'Confirm and add customer';
        const cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'wx-agent-cancel-button';
        cancel.textContent = 'Not now';
        actions.append(confirm, cancel);
        card.appendChild(actions);

        confirm.addEventListener('click', async () => {
            if (busy) return;
            busy = true;
            confirm.disabled = true;
            cancel.disabled = true;
            confirm.textContent = 'Saving…';
            try {
                const response = await fetch(panel.dataset.endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'execute_customer',
                        csrf: panel.dataset.csrf,
                        confirmation_token: confirmationToken,
                        customer,
                    }),
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.error || 'The customer could not be saved.');
                recordMessage('assistant', result.message || 'Customer saved.');
                card.remove();
                const destination = safeLocalUrl(result.redirect);
                if (destination) window.location.assign(destination.href);
            } catch (error) {
                recordMessage('assistant', error.message || 'The customer could not be saved. Please try again.');
                confirm.disabled = false;
                cancel.disabled = false;
                confirm.textContent = 'Confirm and add customer';
            } finally {
                busy = false;
            }
        });
        cancel.addEventListener('click', () => {
            card.remove();
            recordMessage('assistant', 'No problem. Nothing was saved. You can change the details or ask me for something else.');
        });
        messagesElement.appendChild(card);
        messagesElement.scrollTop = messagesElement.scrollHeight;
    }

    function showInventory(products) {
        if (!Array.isArray(products) || products.length === 0) return;
        const card = document.createElement('section');
        card.className = 'wx-agent-inventory';
        const heading = document.createElement('strong');
        heading.textContent = 'Live inventory';
        const list = document.createElement('ul');
        products.forEach(product => {
            if (!product || typeof product.name !== 'string') return;
            const item = document.createElement('li');
            const details = document.createElement('span');
            details.className = 'wx-agent-inventory-name';
            const name = document.createElement('strong');
            name.textContent = product.name;
            const composition = document.createElement('span');
            composition.textContent = typeof product.composition === 'string' ? product.composition : '';
            details.append(name, composition);
            const stock = document.createElement('span');
            stock.className = 'wx-agent-inventory-stock';
            stock.textContent = `${Number(product.stock)} in stock`;
            const price = document.createElement('span');
            price.className = 'wx-agent-inventory-price';
            price.textContent = `Rs. ${Number(product.sale_rate).toFixed(2)}`;
            item.append(details, stock, price);
            list.appendChild(item);
        });
        if (!list.childElementCount) return;
        card.append(heading, list);
        messagesElement.appendChild(card);
        messagesElement.scrollTop = messagesElement.scrollHeight;
    }

    async function sendMessage(text) {
        if (busy || !text.trim()) return;
        panel.querySelector('.wx-agent-suggestions')?.remove();
        recordMessage('user', text);
        busy = true;
        input.disabled = true;
        form.querySelector('button[type="submit"]').disabled = true;
        const typing = document.createElement('div');
        typing.className = 'wx-agent-typing';
        typing.setAttribute('role', 'status');
        typing.textContent = 'Finding the right words…';
        messagesElement.appendChild(typing);
        messagesElement.scrollTop = messagesElement.scrollHeight;
        try {
            const response = await fetch(panel.dataset.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'chat',
                    csrf: panel.dataset.csrf,
                    page: window.location.pathname.split('/').pop(),
                    formFields: formContext(),
                    messages: history.slice(-maxContextMessages),
                }),
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || 'The assistant could not answer just now.');
            const reply = result.message || 'I’m ready when you are.';
            recordMessage('assistant', reply);
            if (result.fill) {
                const count = applyFill(result.fill);
                if (count === 0) recordMessage('assistant', 'I couldn’t match those details to fields that are still visible. No data was changed.');
            }
            if (result.inventory) showInventory(result.inventory);
            if (result.confirm_customer) makeConfirmation(result.confirm_customer, result.confirmation_token);
            if (result.navigate) {
                const destination = safeLocalUrl(result.navigate);
                if (destination) window.location.assign(destination.href);
            }
        } catch (error) {
            addMessage('assistant', error.message || 'Something went wrong while I was thinking. Please try again.');
        } finally {
            typing.remove();
            busy = false;
            input.disabled = false;
            form.querySelector('button[type="submit"]').disabled = false;
            if (!panel.hidden) input.focus();
        }
    }

    function openPanel() {
        if (closeTimer !== null) {
            window.clearTimeout(closeTimer);
            closeTimer = null;
        }
        panel.hidden = false;
        panel.classList.remove('is-closing', 'is-opening');
        document.body.classList.add('wx-agent-open');
        openButton.setAttribute('aria-expanded', 'true');
        showGreeting();
        input.focus();
        window.requestAnimationFrame(() => {
            if (!panel.hidden) panel.classList.add('is-opening');
        });
    }

    function finishClose() {
        if (!panel.classList.contains('is-closing')) return;
        panel.hidden = true;
        panel.classList.remove('is-closing');
        document.body.classList.remove('wx-agent-open');
        openButton.setAttribute('aria-expanded', 'false');
        closeTimer = null;
    }

    function closePanel(restoreFocus) {
        if (panel.hidden || panel.classList.contains('is-closing')) return;
        panel.classList.remove('is-opening');
        panel.classList.add('is-closing');
        document.body.classList.remove('wx-agent-open');
        openButton.setAttribute('aria-expanded', 'false');
        closeTimer = window.setTimeout(finishClose, 320);
        if (restoreFocus) openButton.focus();
    }

    openButton.addEventListener('click', openPanel);
    closeButton.addEventListener('click', () => {
        closePanel(true);
    });
    newChatButton.addEventListener('click', () => {
        if (busy) return;
        history.length = 0;
        saveHistory();
        messagesElement.replaceChildren();
        greeted = false;
        showGreeting();
        input.focus();
    });
    panel.addEventListener('animationend', event => {
        if (event.target === panel && panel.classList.contains('is-closing')) finishClose();
    });
    panel.addEventListener('click', event => {
        if (!(event.target instanceof Element)) return;
        const suggestion = event.target.closest('[data-agent-suggestion]');
        if (!suggestion || busy) return;
        const text = suggestion.dataset.agentSuggestion || '';
        panel.querySelector('.wx-agent-suggestions')?.remove();
        sendMessage(text);
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !panel.hidden) {
            closePanel(true);
        }
    });
    form.addEventListener('submit', event => {
        event.preventDefault();
        const text = input.value.trim();
        if (!text || busy) return;
        panel.querySelector('.wx-agent-suggestions')?.remove();
        input.value = '';
        input.style.height = '';
        sendMessage(text);
    });
    input.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            form.requestSubmit();
        }
    });
    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = `${Math.min(input.scrollHeight, 120)}px`;
    });
})();

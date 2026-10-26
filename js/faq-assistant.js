/**
 * Imali Smart - Interactive AI FAQ Assistant
 * Powers conversational Q&A using Gemini AI with Knowledge Base fallback.
 */
document.addEventListener('DOMContentLoaded', () => {
    const chatContainer = document.querySelector('[data-faq-assistant]');
    if (!chatContainer) return;

    const chatLog = chatContainer.querySelector('[data-faq-log]');
    const form = chatContainer.querySelector('[data-faq-form]');
    const input = chatContainer.querySelector('[name="question"]');
    const submitBtn = form ? form.querySelector('button[type="submit"]') : null;
    const statusEl = chatContainer.querySelector('[data-faq-status]');
    const clearBtn = chatContainer.querySelector('[data-faq-clear]');
    const langSelect = chatContainer.querySelector('[name="language"]');
    const consentCheck = chatContainer.querySelector('[name="consent"]');
    const csrfToken = chatContainer.querySelector('[name="csrf_token"]')?.value || '';

    let conversationHistory = [];

    // Helper: format markdown-like text to safe HTML
    function formatText(text) {
        if (!text) return '';
        // Escape HTML entities first
        let html = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        // Headers
        html = html.replace(/^### (.*$)/gim, '<h4 style="margin: 12px 0 6px; color: var(--ink); font-size: 1.05rem;">$1</h4>');
        html = html.replace(/^## (.*$)/gim, '<h3 style="margin: 14px 0 8px; color: var(--ink); font-size: 1.15rem;">$1</h3>');

        // Bold
        html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        // Italic
        html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');

        // Bullet lists
        const lines = html.split('\n');
        let inList = false;
        let processed = [];

        for (let line of lines) {
            const trimmed = line.trim();
            if (trimmed.startsWith('• ') || trimmed.startsWith('- ') || trimmed.startsWith('* ')) {
                if (!inList) {
                    processed.push('<ul style="margin: 8px 0; padding-left: 20px; line-height: 1.6;">');
                    inList = true;
                }
                processed.push('<li style="margin-bottom: 4px;">' + trimmed.substring(2) + '</li>');
            } else {
                if (inList) {
                    processed.push('</ul>');
                    inList = false;
                }
                if (trimmed !== '') {
                    processed.push('<p style="margin: 6px 0; line-height: 1.6;">' + trimmed + '</p>');
                }
            }
        }
        if (inList) {
            processed.push('</ul>');
        }

        return processed.join('');
    }

    // Append message to chat log
    function appendMessage(role, text, source = '', followUps = []) {
        const msg = document.createElement('div');
        msg.className = `faq-chat-msg faq-chat-msg--${role}`;
        msg.style.marginBottom = '18px';
        msg.style.display = 'flex';
        msg.style.flexDirection = 'column';
        msg.style.alignItems = role === 'user' ? 'flex-end' : 'flex-start';

        const bubble = document.createElement('div');
        bubble.style.maxWidth = '85%';
        bubble.style.padding = '14px 18px';
        bubble.style.borderRadius = role === 'user' ? '16px 16px 4px 16px' : '16px 16px 16px 4px';
        bubble.style.fontSize = '0.92rem';
        bubble.style.lineHeight = '1.6';

        if (role === 'user') {
            bubble.style.background = 'var(--brand-navy, #12305A)';
            bubble.style.color = '#FFFFFF';
            bubble.textContent = text;
        } else {
            bubble.style.background = 'var(--paper-deep, #F1F5F9)';
            bubble.style.color = 'var(--ink, #172033)';
            bubble.style.border = '1px solid var(--line, #E2E8F0)';
            bubble.innerHTML = formatText(text);

            if (source) {
                const meta = document.createElement('div');
                meta.style.marginTop = '10px';
                meta.style.paddingTop = '8px';
                meta.style.borderTop = '1px dashed var(--line-strong, #CBD5E1)';
                meta.style.display = 'flex';
                meta.style.alignItems = 'center';
                meta.style.gap = '8px';
                meta.style.fontSize = '0.75rem';
                meta.style.color = 'var(--ink-faint, #64748B)';
                meta.innerHTML = `<span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #10B981;"></span> Powered by ${source}`;
                bubble.appendChild(meta);
            }

            // Follow-up suggestions
            if (followUps && followUps.length > 0) {
                const followUpBox = document.createElement('div');
                followUpBox.style.marginTop = '12px';
                followUpBox.style.display = 'flex';
                followUpBox.style.flexWrap = 'wrap';
                followUpBox.style.gap = '8px';

                const label = document.createElement('span');
                label.style.fontSize = '0.78rem';
                label.style.fontWeight = '600';
                label.style.color = 'var(--ink-soft, #475569)';
                label.style.width = '100%';
                label.textContent = 'Suggested Follow-up:';
                followUpBox.appendChild(label);

                followUps.forEach(q => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'faq-chip-btn';
                    btn.style.padding = '6px 12px';
                    btn.style.fontSize = '0.8rem';
                    btn.style.borderRadius = '20px';
                    btn.style.border = '1px solid var(--line-strong, #CBD5E1)';
                    btn.style.background = 'var(--panel, #FFFFFF)';
                    btn.style.color = 'var(--ink, #172033)';
                    btn.style.cursor = 'pointer';
                    btn.style.transition = 'all 0.15s ease';
                    btn.textContent = q;

                    btn.addEventListener('mouseenter', () => {
                        btn.style.background = 'var(--brand-navy, #12305A)';
                        btn.style.color = '#FFFFFF';
                        btn.style.borderColor = 'var(--brand-navy, #12305A)';
                    });
                    btn.addEventListener('mouseleave', () => {
                        btn.style.background = 'var(--panel, #FFFFFF)';
                        btn.style.color = 'var(--ink, #172033)';
                        btn.style.borderColor = 'var(--line-strong, #CBD5E1)';
                    });

                    btn.addEventListener('click', () => {
                        submitQuestion(q);
                    });

                    followUpBox.appendChild(btn);
                });

                bubble.appendChild(followUpBox);
            }
        }

        msg.appendChild(bubble);
        chatLog.appendChild(msg);
        chatLog.scrollTop = chatLog.scrollHeight;
    }

    // Typing loading indicator
    function showTypingIndicator() {
        const id = 'faq-typing-indicator';
        const existing = document.getElementById(id);
        if (existing) return existing;

        const indicator = document.createElement('div');
        indicator.id = id;
        indicator.style.marginBottom = '18px';
        indicator.style.display = 'flex';
        indicator.style.alignItems = 'center';
        indicator.style.gap = '8px';
        indicator.style.padding = '12px 16px';
        indicator.style.borderRadius = '16px';
        indicator.style.background = 'var(--paper-deep, #F1F5F9)';
        indicator.style.border = '1px solid var(--line, #E2E8F0)';
        indicator.style.width = 'fit-content';
        indicator.style.color = 'var(--ink-soft, #475569)';
        indicator.style.fontSize = '0.85rem';

        indicator.innerHTML = `
            <span style="display: inline-flex; gap: 4px;">
                <span class="faq-pulse-dot" style="width: 8px; height: 8px; border-radius: 50%; background: var(--brand-green, #16A34A); animation: faqPulse 1.2s infinite ease-in-out;"></span>
                <span class="faq-pulse-dot" style="width: 8px; height: 8px; border-radius: 50%; background: var(--brand-green, #16A34A); animation: faqPulse 1.2s infinite ease-in-out; animation-delay: 0.2s;"></span>
                <span class="faq-pulse-dot" style="width: 8px; height: 8px; border-radius: 50%; background: var(--brand-green, #16A34A); animation: faqPulse 1.2s infinite ease-in-out; animation-delay: 0.4s;"></span>
            </span>
            <span style="font-weight: 500;">Imali Smart Assistant is analyzing...</span>
        `;

        chatLog.appendChild(indicator);
        chatLog.scrollTop = chatLog.scrollHeight;
        return indicator;
    }

    function removeTypingIndicator() {
        const indicator = document.getElementById('faq-typing-indicator');
        if (indicator) indicator.remove();
    }

    function appendInsights(data) {
        const recommendations = Array.isArray(data.recommendations) ? data.recommendations : [];
        const fraudSignals = Array.isArray(data.fraud_signals) ? data.fraud_signals : [];
        if (!recommendations.length && !fraudSignals.length) return;

        const box = document.createElement('div');
        box.className = 'faq-ai-insights';
        box.style.cssText = 'margin:0 0 18px;padding:14px 16px;border:1px solid var(--line,#E2E8F0);border-radius:14px;background:var(--panel,#fff);font-size:.86rem;line-height:1.55;';
        const heading = document.createElement('strong');
        heading.textContent = 'Personalised account signals';
        box.appendChild(heading);

        if (recommendations.length) {
            const title = document.createElement('div');
            title.textContent = 'Recommendations';
            title.style.cssText = 'margin-top:8px;font-weight:700;color:var(--brand-green,#15803d);';
            box.appendChild(title);
            const list = document.createElement('ul');
            list.style.cssText = 'margin:4px 0 0;padding-left:20px;';
            recommendations.slice(0, 4).forEach(item => { const li = document.createElement('li'); li.textContent = item; list.appendChild(li); });
            box.appendChild(list);
        }
        if (fraudSignals.length) {
            const title = document.createElement('div');
            title.textContent = 'Possible unusual activity — verify before taking action';
            title.style.cssText = 'margin-top:10px;font-weight:700;color:#b45309;';
            box.appendChild(title);
            const list = document.createElement('ul');
            list.style.cssText = 'margin:4px 0 0;padding-left:20px;';
            fraudSignals.slice(0, 5).forEach(signal => { const li = document.createElement('li'); li.textContent = `${signal.date || ''} · ${signal.description || 'Transaction'}: ${signal.message || 'Please verify this transaction with your bank.'}`; list.appendChild(li); });
            box.appendChild(list);
        }
        chatLog.appendChild(box);
        chatLog.scrollTop = chatLog.scrollHeight;
    }

    // Submit question flow
    async function submitQuestion(questionText) {
        const text = (questionText || input.value || '').trim();
        if (!text) return;

        appendMessage('user', text);
        if (input) input.value = '';

        conversationHistory.push({ role: 'user', text: text });

        if (submitBtn) submitBtn.disabled = true;
        if (statusEl) statusEl.textContent = 'Consulting financial engine...';

        showTypingIndicator();

        try {
            const formData = new FormData();
            formData.append('question', text);
            formData.append('csrf_token', csrfToken);
            if (langSelect) formData.append('language', langSelect.value);
            const consentGranted = consentCheck ? (consentCheck.type === 'checkbox' ? consentCheck.checked : consentCheck.value === '1') : true;
            formData.append('consent', consentGranted ? '1' : '0');
            formData.append('history', JSON.stringify(conversationHistory.slice(-6)));

            // Give up after 35 seconds so the chat never hangs.
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 35000);
            let res;
            try {
                res = await fetch('api/ai/ask.php', {
                    method: 'POST',
                    body: formData,
                    signal: controller.signal,
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json'
                    }
                });
            } finally {
                clearTimeout(timeoutId);
            }

            removeTypingIndicator();

            if (!res.ok) {
                throw new Error(`HTTP ${res.status}`);
            }

            const data = await res.json();
            if (data.ok && data.answer) {
                conversationHistory.push({ role: 'assistant', text: data.answer });
                const offline = typeof data.ai_status === 'string' && data.ai_status.startsWith('offline:');
                const why = offline ? ' (live AI offline: ' + data.ai_status.slice(8) + ')' : '';
                appendMessage('assistant', data.answer, (data.source || 'Imali Smart AI') + why, data.follow_ups || []);
                appendInsights(data);
            } else {
                appendMessage('assistant', data.error || 'Sorry, I could not generate an answer. Please try asking again.');
            }
        } catch (err) {
            removeTypingIndicator();
            appendMessage('assistant', err && err.name === 'AbortError'
                ? 'That took longer than expected. Please try again, or pick one of the suggested topics below.'
                : 'The assistant could not be reached. Check your connection and try again, or pick one of the suggested topics below.');
        } finally {
            if (submitBtn) submitBtn.disabled = false;
            if (statusEl) statusEl.textContent = '';
            if (input) input.focus();
        }
    }

    // Handle form submit
    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            submitQuestion();
        });
    }

    // Quick chip clicks
    chatContainer.querySelectorAll('[data-faq-prompt]').forEach(chip => {
        chip.addEventListener('click', () => {
            const prompt = chip.getAttribute('data-faq-prompt');
            if (prompt) {
                submitQuestion(prompt);
            }
        });
    });

    // Reset button
    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            conversationHistory = [];
            chatLog.innerHTML = `
                <div class="faq-chat-msg faq-chat-msg--assistant" style="margin-bottom: 18px; display: flex; flex-direction: column; align-items: flex-start;">
                    <div style="max-width: 85%; padding: 14px 18px; border-radius: 16px 16px 16px 4px; font-size: 0.92rem; line-height: 1.6; background: var(--paper-deep, #F1F5F9); color: var(--ink, #172033); border: 1px solid var(--line, #E2E8F0);">
                        <p style="margin: 0;"><strong>Sawubona! Hello.</strong> I am your Imali Smart AI financial assistant. Ask me anything about budgeting (like 50/30/20), recording multi-million rand transactions, downloading executive PDF/Excel statements, till cash-up reconciliations, or South African tax tips.</p>
                    </div>
                </div>
            `;
            if (input) {
                input.value = '';
                input.focus();
            }
        });
    }
});

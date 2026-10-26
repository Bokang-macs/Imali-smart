/**
 * Scam awareness page: search the scam guides and run the "Spot the scam" quiz.
 * Plain JavaScript, nothing is stored or sent anywhere.
 */
(() => {
    'use strict';

    // ---- Search the guides ----
    const search = document.querySelector('[data-scam-search]');
    const cards = [...document.querySelectorAll('[data-scam-card]')];
    const empty = document.querySelector('[data-scam-empty]');
    if (search && cards.length) {
        search.addEventListener('input', () => {
            const term = search.value.trim().toLowerCase();
            let shown = 0;
            cards.forEach(card => {
                const match = term === '' || (card.dataset.text || '').includes(term);
                card.hidden = !match;
                if (match) shown++;
                if (term !== '' && match && shown === 1) card.open = true;
                if (term === '') card.open = false;
            });
            if (empty) empty.hidden = shown !== 0;
        });
    }

    // ---- Quiz ----
    const root = document.querySelector('[data-quiz]');
    if (!root) return;

    const questions = [
        {
            text: 'SMS: "FNB: Your account is locked. Verify now at fnb-secure-login.co to avoid closure within 24 hours."',
            scam: true,
            why: 'Urgency, a threat of closure and a link to an unofficial website are classic phishing signs. Banks do not ask you to log in through links in SMSs.'
        },
        {
            text: 'A buyer sends a screenshot saying "Payment successful, pending 24h" and asks you to deliver the goods today.',
            scam: true,
            why: 'A screenshot is not money. Only hand over goods when the payment is showing in your own bank account.'
        },
        {
            text: 'Your bank app sends a notification: "A payment of R450 was made with your card at a shop. Not you? Open the app or call the number on the back of your card." There is no link.',
            scam: false,
            why: 'This message does not ask for secrets or send you to a link. It tells you to use the app or the number on your card. When in doubt, still check through your banking app yourself.'
        },
        {
            text: 'WhatsApp from an unknown number: "Hi Mum, I dropped my phone and this is my new number. Can you pay my rent urgently? I will pay you back tonight."',
            scam: true,
            why: 'Scammers pretend to be family from a new number. Call your child on their old number before sending money.'
        },
        {
            text: 'Email from refunds@sars.co.za: "You are owed R8,412. Click here and enter your banking details to receive your refund."',
            scam: true,
            why: 'SARS does not ask for banking details by email or send links. Real SARS emails use sars.gov.za, and refunds are handled through eFiling or the SARS MobiApp.'
        },
        {
            text: 'A job advert says: "Cleaner needed. After your interview you must pay R350 for your uniform and training before you can start."',
            scam: true,
            why: 'Real employers do not charge you to hire you. Upfront fees for jobs are a common scam.'
        }
    ];

    const body = root.querySelector('[data-quiz-body]');
    const message = root.querySelector('[data-quiz-message]');
    const feedback = root.querySelector('[data-quiz-feedback]');
    const next = root.querySelector('[data-quiz-next]');
    const result = root.querySelector('[data-quiz-result]');
    const score = root.querySelector('[data-quiz-score]');
    const progress = root.querySelector('[data-quiz-progress]');
    const answers = [...root.querySelectorAll('[data-answer]')];
    const restart = root.querySelector('[data-quiz-restart]');

    let index = 0;
    let correct = 0;
    let order = [];

    const shuffle = (list) => {
        const copy = list.slice();
        for (let i = copy.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [copy[i], copy[j]] = [copy[j], copy[i]];
        }
        return copy;
    };

    const show = () => {
        const q = order[index];
        message.textContent = q.text;
        feedback.hidden = true;
        feedback.className = 'scam-quiz__feedback';
        next.hidden = true;
        answers.forEach(btn => { btn.disabled = false; btn.classList.remove('is-picked'); });
        progress.textContent = 'Message ' + (index + 1) + ' of ' + order.length;
    };

    const start = () => {
        order = shuffle(questions);
        index = 0;
        correct = 0;
        result.hidden = true;
        body.hidden = false;
        show();
    };

    answers.forEach(btn => {
        btn.addEventListener('click', () => {
            const q = order[index];
            const saidScam = btn.dataset.answer === 'scam';
            const right = saidScam === q.scam;
            if (right) correct++;
            answers.forEach(b => { b.disabled = true; });
            btn.classList.add('is-picked');
            feedback.hidden = false;
            feedback.classList.add(right ? 'is-right' : 'is-wrong');
            feedback.textContent = (right ? 'Correct. ' : 'Not quite. ') + (q.scam ? 'This is a scam. ' : 'This looks genuine. ') + q.why;
            next.textContent = index === order.length - 1 ? 'See my result' : 'Next message';
            next.hidden = false;
            next.focus();
        });
    });

    next.addEventListener('click', () => {
        if (index < order.length - 1) {
            index++;
            show();
        } else {
            body.hidden = true;
            result.hidden = false;
            progress.textContent = 'Finished';
            score.textContent = 'You got ' + correct + ' of ' + order.length + ' right.';
        }
    });

    restart?.addEventListener('click', start);
    start();
})();

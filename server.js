/**
 * iMali Smart - Full Stack Express Server (JavaScript).
 * Serves the authentic PHP web app on port 3000 via PHP built-in server proxy,
 * and handles server-side Gemini AI features with @google/genai.
 */
import express from 'express';
import http from 'node:http';
import { spawn, execSync } from 'node:child_process';
import { GoogleGenAI } from '@google/genai';
import dotenv from 'dotenv';

dotenv.config();

// Process-wide error guards to prevent unhandled error crashes
process.on('uncaughtException', (err) => {
  console.error('[iMali Server] Uncaught exception:', err?.message || err);
});
process.on('unhandledRejection', (reason, promise) => {
  console.error('[iMali Server] Unhandled rejection:', reason);
});

// Dynamic port resolution (supporting --port CLI argument from build runner)
const args = process.argv.slice(2);
const portIndex = args.indexOf('--port');
const PORT = portIndex !== -1 && args[portIndex + 1] ? parseInt(args[portIndex + 1], 10) : (process.env.PORT || 3000);
const PHP_PORT = 8088;
const PHP_HOST = '127.0.0.1';

// Server-side Gemini AI Client
const apiKey = process.env.GEMINI_API_KEY || '';
const ai = apiKey
  ? new GoogleGenAI({
      apiKey: apiKey,
      httpOptions: {
        headers: {
          'User-Agent': 'aistudio-build',
        },
      },
    })
  : null;

// Ensure PHP background process is running
let phpProcess = null;

function startPhpServer() {
  try {
    try {
      execSync('/etc/init.d/mariadb status >/dev/null 2>&1 || /etc/init.d/mariadb start >/dev/null 2>&1', { stdio: 'ignore' });
    } catch (_) {}
    phpProcess = spawn('php', ['-S', `${PHP_HOST}:${PHP_PORT}`, '-t', '.'], {
      cwd: process.cwd(),
      env: {
        ...process.env,
        GEMINI_API_KEY: process.env.GEMINI_API_KEY || '',
        GEMINI_MODEL: process.env.GEMINI_MODEL || 'gemini-flash-latest',
        DB_FALLBACK_SQLITE: 'true',
      },
      stdio: 'pipe',
    });

    phpProcess.on('error', (err) => {
      console.error('[iMali Server] PHP process error:', err.message);
    });

    phpProcess.on('exit', (code) => {
      console.log(`[iMali Server] PHP process exited with code ${code}, restarting...`);
      setTimeout(startPhpServer, 1000);
    });
  } catch (err) {
    console.error('[iMali Server] Failed to spawn PHP server:', err);
  }
}

startPhpServer();

const app = express();

// Curated South African Financial Curriculum Question Bank
const CURATED_QUIZ_BANK = {
  beginner: [
    {
      id: 1,
      title: 'Weekly Envelope Allocation',
      scenario: 'Thabo earns R 3,200 this week from part-time retail work. His essential fixed transport and groceries cost R 1,950.',
      question: 'How much discretionary money remains for emergency savings and flexible expenses?',
      calculation_formula: 'R 3,200 (Total earnings) - R 1,950 (Committed essential costs) = R 1,250',
      options: ['R 1,250', 'R 1,450', 'R 950', 'R 5,150'],
      correct_index: 0,
      explanation: 'Subtract committed living expenses (R 1,950) directly from total net earnings (R 3,200) to find the remaining surplus of R 1,250. Directing at least 20% (R 250) of this remainder to an emergency buffer creates sustainable security.',
      key_takeaway: 'Always fund fixed living essentials before assigning flexible discretionary cash.',
      difficulty: 'beginner',
    },
    {
      id: 2,
      title: 'The 50/30/20 Framework in Practice',
      scenario: 'Nomsa receives a monthly net salary of R 14,000 into her bank account. She wants to adopt the 50/30/20 budgeting rule.',
      question: 'What is the maximum amount Nomsa should allocate toward her "Needs" (housing, utilities, groceries, transport)?',
      calculation_formula: '50% of R 14,000 = 0.50 × 14,000 = R 7,000',
      options: ['R 7,000', 'R 4,200', 'R 2,800', 'R 9,500'],
      correct_index: 0,
      explanation: 'Under the 50/30/20 guideline: 50% = Needs (R 7,000), 30% = Wants (R 4,200), and 20% = Savings / Debt Snowball (R 2,800).',
      key_takeaway: 'Capping needs at 50% of take-home pay prevents lifestyle inflation and paycheck-to-paycheck stress.',
      difficulty: 'beginner',
    },
    {
      id: 3,
      title: 'Debit Order & Bank Charge Protection',
      scenario: 'Sipho has an active debit order of R 450 scheduled for the 25th. His account balance on the morning of the 25th is R 410.',
      question: 'What will happen if the debit order bounces due to insufficient funds?',
      calculation_formula: 'Unpaid debit order penalty (often R 50 to R 115 depending on bank) + unpaid creditor fee + negative impact on credit record.',
      options: [
        'The bank charges an unpaid debit order fee and the missed payment damages his credit score',
        'The bank pays the remaining R 40 for free with zero penalties',
        'The creditor automatically cancels the debt contract without consequence',
        'Nothing happens until next month',
      ],
      correct_index: 0,
      explanation: 'Bounced debit orders attract penalty dispute charges from your bank and mark a default on your credit record with credit bureaus like TransUnion and Experian.',
      key_takeaway: 'Maintain an emergency buffer in your cheque account so scheduled debits never bounce.',
      difficulty: 'beginner',
    },
    {
      id: 4,
      title: 'Needs vs. Wants Trade-Off',
      scenario: 'Ayanda has R 600 remaining before payday. She needs R 350 for her monthly work train ticket and wants a pair of designer branded sunglasses on sale for R 400.',
      question: 'What is the financially responsible decision?',
      calculation_formula: 'Available R 600 - Train Ticket R 350 = R 250 surplus remaining. Sunglasses cost R 400 > R 250.',
      options: [
        'Purchase the train ticket first; save the remaining R 250 toward buying the sunglasses next month',
        'Buy the sunglasses on credit and skip buying the train ticket',
        'Borrow R 200 from a loan shark to buy both items today',
        'Spend the full R 600 on dining out because it was a tough week',
      ],
      correct_index: 0,
      explanation: 'Work transport directly enables your continued income. Buying wants before needs risks losing your job or falling into predatory high-interest debt.',
      key_takeaway: 'Income-generating tools (like transit tickets) take absolute priority over depreciating lifestyle items.',
      difficulty: 'beginner',
    },
    {
      id: 5,
      title: 'Starter Emergency Buffer Goal',
      scenario: 'Kagiso wants to build a Level 1 Starter Emergency Cushion to avoid using store cards for minor breakdowns.',
      question: 'What is the recommended starter emergency cushion target for an entry-level income earner in South Africa?',
      calculation_formula: 'Level 1 target: R 1,500 to R 3,000 for unexpected tyre repair, plumbing, or urgent co-pays.',
      options: ['R 2,500 – R 3,000', 'R 100,000', 'R 50', 'Zero (rely on credit cards)'],
      correct_index: 0,
      explanation: 'A starter cushion of R 2,500 to R 3,000 handles 80% of minor household crises without taking out a micro-loan or payday loan at 60%+ APR.',
      key_takeaway: 'Build a starter emergency cushion before aggressively investing in long-term assets.',
      difficulty: 'beginner',
    },
  ],
  intermediate: [
    {
      id: 1,
      title: 'Compound Interest vs. Inflation',
      scenario: 'Lerato invests R 10,000 in a fixed deposit paying 9.0% annual interest compounded monthly. Inflation averages 5.0% per annum.',
      question: "What is Lerato's approximate real (inflation-adjusted) return after one year?",
      calculation_formula: 'Nominal rate (9.0%) - Inflation rate (5.0%) ≈ 4.0% real return on purchasing power.',
      options: ['Approximately 4.0% real growth', 'Negative 4.0% loss', '14.0% growth', '0.0% unchanged'],
      correct_index: 0,
      explanation: 'Real return measures actual purchasing power gained after subtracting inflation: 9% nominal yield minus 5% inflation gives approximately 4% real wealth accumulation.',
      key_takeaway: 'To build true wealth, your investment yield must consistently beat CPI inflation.',
      difficulty: 'intermediate',
    },
    {
      id: 2,
      title: 'Debt Snowball Ordering',
      scenario: 'Mpho has 3 active debts: Credit Card (R 12,000 at 22%), Clothing Account (R 1,800 at 24%), and Personal Loan (R 35,000 at 18%).',
      question: "Using Dave Ramsey's Debt Snowball method, which debt should Mpho attack first with extra payments?",
      calculation_formula: 'Order debts strictly by balance: 1. Clothing Account (R 1,800), 2. Credit Card (R 12,000), 3. Personal Loan (R 35,000).',
      options: [
        'Clothing Account (R 1,800 balance)',
        'Personal Loan (R 35,000 balance)',
        'Credit Card (R 12,000 balance)',
        'Pay equal R 100 on all three debts',
      ],
      correct_index: 0,
      explanation: 'The Debt Snowball prioritizes the smallest balance first (Clothing Account: R 1,800) regardless of interest rate. Knocking out the smallest debt creates rapid psychological momentum and frees up minimum monthly cashflow to roll into the next debt.',
      key_takeaway: 'Knock out your smallest debt first to build unstoppable psychological momentum.',
      difficulty: 'intermediate',
    },
    {
      id: 3,
      title: 'National Credit Act (NCA) Interest Limits',
      scenario: 'A micro-lender offers a 1-month short-term loan of R 1,000 and demands R 1,600 repayment next month (a 60% monthly interest rate).',
      question: 'Is this legal under the South African National Credit Act (NCA)?',
      calculation_formula: 'NCA max short-term credit rate is 5% per month for the first agreement in a calendar year. 60% per month is predatory and illegal.',
      options: [
        'No, 60% in a single month is predatory and breaches NCA statutory caps',
        'Yes, lenders can charge any interest rate agreed in writing',
        'Yes, provided they hold your ID card as deposit',
        'Only if approved by the local municipality',
      ],
      correct_index: 0,
      explanation: 'Under South African regulations administered by the National Credit Regulator (NCR), short-term micro-loans have strict interest and fee caps. Demanding 60% monthly interest is illegal mashonisa activity.',
      key_takeaway: 'Never borrow from lenders who exceed NCA rate caps or demand bank cards as collateral.',
      difficulty: 'intermediate',
    },
    {
      id: 4,
      title: 'True Cost of Financing a Vehicle',
      scenario: 'Bongani buys a used vehicle for R 120,000 with a 35% balloon payment at the end of a 60-month term.',
      question: 'What is the danger of a balloon payment?',
      calculation_formula: '35% of R 120,000 = R 42,000 lump sum due at end of term, while paying interest on R 42,000 for the full 5 years.',
      options: [
        'A lump sum of R 42,000 must be paid or refinanced at year 5, incurring double interest',
        'The car becomes free after 60 months with no further obligations',
        'The bank pays the remaining 35% on your behalf',
        'The balloon amount is subtracted from your original deposit',
      ],
      correct_index: 0,
      explanation: 'A balloon payment reduces monthly instalments artificially, but keeps you paying interest on the balloon portion (R 42,000) for the entire 5 years. At month 60, you must settle R 42,000 in cash or re-finance.',
      key_takeaway: 'Avoid large balloon payments that trap you in continuous car debt cycles.',
      difficulty: 'intermediate',
    },
    {
      id: 5,
      title: 'Stokvel Governance & Yield',
      scenario: 'A 10-member grocery stokvel collects R 1,000 per member monthly into a 32-day notice account yielding 8.5% annual interest.',
      question: 'How much total principal capital does the stokvel accumulate over a 12-month cycle before interest?',
      calculation_formula: '10 members × R 1,000/month × 12 months = R 120,000 total contributions.',
      options: ['R 120,000', 'R 12,000', 'R 100,000', 'R 144,000'],
      correct_index: 0,
      explanation: '10 members contributing R 1,000 monthly pool R 10,000 per month, totaling R 120,000 over 12 months. When kept in an interest-bearing notice account rather than a zero-interest savings tin, members earn thousands in extra interest bonuses.',
      key_takeaway: 'Formalize stokvel funds in compliant business notice accounts to earn collective yield.',
      difficulty: 'intermediate',
    },
  ],
  advanced: [
    {
      id: 1,
      title: 'Gross Margin vs. Markup Calculation',
      scenario: 'A fast-food entrepreneur prepares gourmet kota sandwiches. The true direct cost (bread, polony, cheese, egg, packaging) is R 30 per kota. She sells each kota for R 50.',
      question: 'What is the Markup percentage and what is the Gross Profit Margin percentage?',
      calculation_formula: 'Profit = R 50 - R 30 = R 20. Markup = (20 / 30) × 100 = 66.7%. Margin = (20 / 50) × 100 = 40.0%.',
      options: [
        'Markup is 66.7%, Gross Margin is 40.0%',
        'Markup is 40.0%, Gross Margin is 66.7%',
        'Both are 20.0%',
        'Markup is 50.0%, Gross Margin is 50.0%',
      ],
      correct_index: 0,
      explanation: 'Markup is calculated on cost: (R 20 / R 30) = 66.7%. Gross Profit Margin is calculated on selling price: (R 20 / R 50) = 40.0%. Confusing margin with markup is one of the leading causes of small business bankruptcy.',
      key_takeaway: 'Never confuse markup with margin; margin reflects the actual percentage of sales kept as profit.',
      difficulty: 'advanced',
    },
    {
      id: 2,
      title: 'Break-Even Sales Volume',
      scenario: 'A township hair salon has fixed monthly overheads of R 8,000 (rent, power, booking software). Each styling appointment sells for R 250 and consumes R 50 in direct variable products.',
      question: 'How many client appointments must the salon perform each month just to break even (cover all costs)?',
      calculation_formula: 'Contribution Margin per client = R 250 - R 50 = R 200. Break-even volume = R 8,000 / R 200 = 40 appointments.',
      options: ['40 appointments', '32 appointments', '50 appointments', '160 appointments'],
      correct_index: 0,
      explanation: 'Each client contributes R 200 (R 250 price - R 50 variable cost) toward fixed rent. Dividing R 8,000 by R 200 gives 40 appointments per month (or roughly 10 appointments per week) to reach zero-profit break-even.',
      key_takeaway: 'Knowing your break-even volume sets your non-negotiable minimum weekly sales target.',
      difficulty: 'advanced',
    },
    {
      id: 3,
      title: 'SARS Turnover Tax for Micro-Enterprises',
      scenario: 'An electrical repair enterprise registered for SARS Turnover Tax generated R 450,000 in total qualifying sales during the tax year.',
      question: 'Using the SARS Turnover Tax bracket (R 335,001 – R 500,000: 1% of amount above R 335,000), what is the annual tax liability?',
      calculation_formula: 'Taxable excess = R 450,000 - R 335,000 = R 115,000. Tax owed = 1% of R 115,000 = R 1,150.',
      options: ['R 1,150', 'R 4,500', 'R 126,000 (standard 28% corporate tax)', 'Zero'],
      correct_index: 0,
      explanation: 'SARS Turnover Tax replaces normal income tax, CGT, and provisional tax for businesses under R1m turnover. On R 450,000 turnover, the tax is just 1% of the excess over R 335,000, which equals R 1,150.',
      key_takeaway: 'Qualifying micro-enterprises save significant compliance costs by opting into SARS Turnover Tax.',
      difficulty: 'advanced',
    },
    {
      id: 4,
      title: 'Cash Flow Runway & Working Capital',
      scenario: 'A mobile car wash business has R 18,000 in the bank. Monthly operational costs are R 12,000, while monthly customer revenue is R 6,000 during a rainy winter dip.',
      question: 'What is the net monthly burn rate and how many months of cash runway does the business have?',
      calculation_formula: 'Monthly burn = R 12,000 costs - R 6,000 sales = -R 6,000/month. Runway = R 18,000 / R 6,000 = 3 months.',
      options: [
        'Burn rate is R 6,000/month; Runway is 3 months',
        'Burn rate is R 12,000/month; Runway is 1.5 months',
        'Burn rate is R 6,000/month; Runway is 12 months',
        'The business has unlimited runway',
      ],
      correct_index: 0,
      explanation: 'Net burn rate is the net cash deficit per month (R 12,000 - R 6,000 = R 6,000). At R 6,000 per month, an R 18,000 reserve lasts exactly 3 months before running out of operating cash.',
      key_takeaway: 'Monitor your cash runway closely so you can adjust expenses months before cash runs out.',
      difficulty: 'advanced',
    },
    {
      id: 5,
      title: 'Till Cash-Up Variance & Internal Controls',
      scenario: 'At end-of-day shift closure, a spaza shop register shows R 4,850 in expected cash sales. The physical cash counted in the drawer is R 4,620.',
      question: 'What is the till variance and how should the owner record it?',
      calculation_formula: 'Variance = Actual cash (R 4,620) - Expected cash (R 4,850) = -R 230 (Cash Shortage).',
      options: [
        'A Shortage of R 230; investigate potential cashier change errors or untracked cash payouts',
        'A Surplus of R 230; record as bonus income',
        'The till balances with zero discrepancies',
        'Ignore the difference if under R 500',
      ],
      correct_index: 0,
      explanation: 'Physical counted cash (R 4,620) is R 230 less than expected sales (R 4,850). This is a cash shortage that must be audited for unrecorded petty cash payouts or change errors.',
      key_takeaway: 'Daily till cash-ups catch cash leakages immediately before they compound over months.',
      difficulty: 'advanced',
    },
  ],
};

function getCuratedQuiz(topic, level, count) {
  const pool = CURATED_QUIZ_BANK[level] || CURATED_QUIZ_BANK.beginner;
  return pool.slice(0, Math.min(count, pool.length));
}

// Dedicated Node/Express Gemini Quiz generation route (only parses body for this route)
app.post('/api/gemini/quiz', express.json(), async (req, res) => {
  const topic = req.body?.topic || 'household_budgeting';
  const level = req.body?.level || 'beginner';
  const count = Math.min(10, Math.max(1, parseInt(req.body?.count, 10) || 5));

  // If Gemini client is available, attempt real-time generation with a 3-second timeout race
  if (ai) {
    try {
      const prompt = `You are a master South African financial educator for iMali Smart personal money learning.
Generate ${count} practical, realistic financial multiple-choice questions for ${level} level on the topic: ${topic}.
Every question MUST include:
- id: integer
- title: short string
- scenario: 1-2 sentence South African real-world scenario (use Rand amounts e.g. R 1,500.00, R 250, etc.)
- question: clear calculation or decision question
- calculation_formula: step-by-step arithmetic formula showing the exact math
- options: array of 4 choices (e.g. ["R 750", "R 850", "R 650", "R 1,050"])
- correct_index: integer 0 to 3
- explanation: educational breakdown of why the answer is mathematically correct
- key_takeaway: one sentence practical money rule
- difficulty: "${level}"

Output ONLY a valid JSON array of question objects without markdown wrapping.`;

      const generatePromise = ai.models.generateContent({
        model: 'gemini-flash-lite-latest',
        contents: prompt,
        config: {
          responseMimeType: 'application/json',
          temperature: 0.2,
        },
      });

      const timeoutPromise = new Promise((_, reject) =>
        setTimeout(() => reject(new Error('AI generation timeout')), 8000)
      );

      const response = await Promise.race([generatePromise, timeoutPromise]);
      const text = (response.text || '').trim();
      let questions = JSON.parse(text);
      if (questions && questions.questions && Array.isArray(questions.questions)) {
        questions = questions.questions;
      }

      if (Array.isArray(questions) && questions.length > 0) {
        return res.json({
          ok: true,
          source: 'gemini-js-sdk',
          model_used: 'gemini-flash-lite-latest',
          topic,
          level,
          count: questions.length,
          questions,
        });
      }
    } catch (err) {
      console.warn('[iMali Server] Gemini quiz generation notice (falling back to curated bank):', err?.message || err);
    }
  }

  // Guaranteed, instant response with verified South African financial curriculum questions
  const questions = getCuratedQuiz(topic, level, count);
  return res.json({
    ok: true,
    source: 'curated_bank',
    model_used: 'Imali Smart Financial Engine',
    topic,
    level,
    count: questions.length,
    questions,
  });
});

// In-memory LRU/TTL Cache for frequent Gemini questions to ensure instant response (< 5ms)
const aiChatCache = new Map();
const MAX_AI_CACHE = 300;
const AI_CACHE_TTL_MS = 60 * 60 * 1000; // 1 hour

function getCachedAnswer(key) {
  const item = aiChatCache.get(key);
  if (!item) return null;
  if (Date.now() > item.expiresAt) {
    aiChatCache.delete(key);
    return null;
  }
  return item.value;
}

function setCachedAnswer(key, value) {
  if (aiChatCache.size >= MAX_AI_CACHE) {
    const oldestKey = aiChatCache.keys().next().value;
    if (oldestKey) aiChatCache.delete(oldestKey);
  }
  aiChatCache.set(key, {
    value,
    expiresAt: Date.now() + AI_CACHE_TTL_MS,
  });
}

// Ultra-low latency Gemini Chat / Ask endpoint powered by @google/genai
app.post('/api/gemini/chat', express.json(), async (req, res) => {
  const question = String(req.body?.question || '').trim();
  const language = String(req.body?.language || 'auto');
  const context = req.body?.context || {};
  const history = Array.isArray(req.body?.history) ? req.body.history : [];

  if (!question) {
    return res.status(400).json({ ok: false, error: 'Question is required.' });
  }

  // Fast-path in-memory cache lookup
  const cacheKey = `${language}:${question.toLowerCase().replace(/[^a-z0-9]/g, '')}`;
  const cached = getCachedAnswer(cacheKey);
  if (cached) {
    return res.json({
      ok: true,
      text: cached.text,
      source: 'Gemini 3.1 Flash-Lite (Instant Cache)',
      model_used: 'gemini-3.1-flash-lite',
      cached: true,
      follow_ups: cached.follow_ups || [],
    });
  }

  if (ai) {
    try {
      const languageInstruction = language === 'auto'
        ? 'Reply in the language the user writes in (defaults to English or South African local languages: isiZulu, isiXhosa, Afrikaans, Sepedi, Setswana, Sesotho). Preserve Rand amounts with R symbol (e.g. R 1,500.00).'
        : `Reply strictly and fluently in ${language}. Preserve all Rand amounts with R symbol.`;

      const systemInstruction = `You are 'Imali Smart Assistant', a concise, friendly South African financial advisor.
${languageInstruction}
Provide direct, compact answers (100-180 words max) using South African Rand (R) amounts, bold headings, and clear bullet points.
Cover financial topics (50/30/20, debt snowball, emergency funds, stokvels, SARS tax readiness, till cash-ups, invoice QR codes) and Imali Smart features.
Never request banking PINs, passwords, OTPs, or CVVs. Context: ${JSON.stringify(context)}`;

      const contents = [];
      // Only include last 2-3 turns to keep prompt token size minimal and inference latency low
      for (const turn of history.slice(-3)) {
        const role = turn.role === 'user' ? 'user' : 'model';
        const text = String(turn.text || '').trim();
        if (text) {
          contents.push({ role, parts: [{ text }] });
        }
      }
      contents.push({ role: 'user', parts: [{ text: question }] });

      const generatePromise = ai.models.generateContent({
        model: 'gemini-flash-lite-latest',
        contents,
        config: {
          systemInstruction,
          temperature: 0.2,
          maxOutputTokens: 450,
        },
      });

      const timeoutPromise = new Promise((_, reject) =>
        setTimeout(() => reject(new Error('AI response timeout')), 10000)
      );

      const response = await Promise.race([generatePromise, timeoutPromise]);
      const text = (response.text || '').trim();

      if (text) {
        setCachedAnswer(cacheKey, { text, follow_ups: [] });
        return res.json({
          ok: true,
          text,
          source: 'Gemini Flash-Lite AI',
          model_used: 'gemini-flash-lite-latest',
          cached: false,
          follow_ups: [],
        });
      }
    } catch (err) {
      console.warn('[iMali Server] Gemini chat generation notice:', err?.message || err);
    }
  }

  // Reliable financial engine fallback for South African financial principles
  const qLower = question.toLowerCase();
  let answer = "";
  if (qLower.includes('50/30/20') || qLower.includes('budget') || qLower.includes('allocate')) {
    answer = `**The 50/30/20 Prosperity Blueprint**\n\n• **50% Needs:** Fixed living expenses — shelter, transport, groceries, municipal electricity, and school essentials.\n• **30% Wants:** Discretionary lifestyle spending — family treats, entertainment, and non-essential dining.\n• **20% Wealth Building:** High-impact money moves — debt snowball repayments, emergency buffers, and stokvel contributions.\n\n*Tip:* In Imali Smart, set category envelopes under **Budgets** to track real-time limits.`;
  } else if (qLower.includes('debt') || qLower.includes('snowball') || qLower.includes('loan')) {
    answer = `**The Debt Snowball Momentum Method**\n\n1. **List all debts:** Order credit cards, store accounts, and loans from lowest balance to highest.\n2. **Pay minimums:** Ensure all creditors receive minimum payments to protect your credit score.\n3. **Attack the smallest balance:** Direct all extra discretionary cash toward the smallest balance until cleared.\n4. **Roll it over:** Roll the cleared payment into the next smallest debt for accelerating momentum.`;
  } else if (qLower.includes('emergenc') || qLower.includes('save') || qLower.includes('cushion')) {
    answer = `**3-Tier Emergency Fortress**\n\n• **Tier 1 (Starter Buffer):** Aim for R 1,500 – R 3,000 for immediate unexpected repairs or medical bills.\n• **Tier 2 (1-Month Essentials):** Cover one month of shelter, basic food, and essential utilities.\n• **Tier 3 (3-6 Months Independence):** Comprehensive safety net to protect against job disruption or income shocks.\n\n*Action:* Automate a weekly deposit (e.g. R 150 – R 250) into your **Savings Goals**.`;
  } else {
    answer = `**Imali Smart Financial Intelligence**\n\nManaging money in South Africa requires clear separation between essential fixed costs, flexible lifestyle spending, and intentional future savings.\n\n• **Track Cash Flow:** Review money in vs. money out regularly on the **Dashboard**.\n• **Use Envelopes:** Assign every Rand a purpose before the month begins.\n• **Protect Reserves:** Build an emergency fund before taking on discretionary debt.`;
  }

  return res.json({
    ok: true,
    text: answer,
    source: 'Imali Smart Financial Engine',
    model_used: 'curated_financial_advisor',
    cached: false,
    follow_ups: ['How does the 50/30/20 rule work?', 'What is the fastest way to pay off debts?', 'How do I start an emergency fund?'],
  });
});

// Proxy all other requests to the PHP development server
app.use((req, res) => {
  const reqUrl = req.originalUrl || req.url || '/';
  const targetPath = (reqUrl === '/' || reqUrl.startsWith('/?'))
    ? '/index.php' + (reqUrl.length > 1 ? reqUrl.substring(1) : '')
    : reqUrl;

  const options = {
    hostname: PHP_HOST,
    port: PHP_PORT,
    path: targetPath,
    method: req.method,
    headers: {
      ...req.headers,
      host: req.headers.host || `localhost:${PORT}`,
      'x-forwarded-for': req.socket.remoteAddress || '127.0.0.1',
      'x-forwarded-proto': 'http',
    },
  };

  const proxyReq = http.request(options, (proxyRes) => {
    res.writeHead(proxyRes.statusCode || 200, proxyRes.headers);
    proxyRes.pipe(res, { end: true });
  });

  proxyReq.on('error', (err) => {
    console.error('[iMali Proxy] Error connecting to PHP:', err.message);
    res.status(502).send(
      `<!doctype html><html><head><title>Starting iMali Smart...</title><meta http-equiv="refresh" content="2"></head><body style="font-family:system-ui;padding:40px;text-align:center;"><h2>Starting iMali Smart Financial Suite...</h2><p>Connecting to backend services. Please hold on...</p></body></html>`
    );
  });

  req.pipe(proxyReq, { end: true });
});

const server = app.listen(PORT, '0.0.0.0', () => {
  console.log(`[iMali Server] Running and ready on http://0.0.0.0:${PORT}`);
});

server.on('error', (err) => {
  console.error('[iMali Server] HTTP server listen error:', err);
});

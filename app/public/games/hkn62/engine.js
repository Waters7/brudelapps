/* Integer, fixed-tick rules mirrored by App\Services\Game\HknEngine. */
(function (root) {
    'use strict';
    const FINISH = 62000, HZ = 60, MAX_TICKS = 18000;
    function course(seed) {
        let random = seed, at = 1300, items = [];
        const next = () => (random = (random * 48271) % 2147483647);
        while (at < FINISH - 1200) {
            items.push({ at, width: 28 + next() % 18, height: 32 + next() % 25, hit: false });
            at += 650 + next() % 420;
        }
        return items;
    }
    function create(seed, questions = []) {
        return { seed, tick: 0, distance: 0, y: 0, velocity: 0, lives: 3, freeze: 0,
            invincible: 0, next: 0, status: 'running', obstacles: course(seed), jumps: [],
            questions, quizIndex: 0, quiz: false, quizTicks: 0, answers: [], feedback: null, feedbackTicks: 0 };
    }
    function step(s, jump = false, answer = null) {
        if (s.status !== 'running') return;
        s.tick++;
        if (s.tick >= MAX_TICKS) { s.status = 'lost'; return; }
        if (s.feedbackTicks > 0) { s.feedbackTicks--; return; }
        if (s.quiz) {
            s.quizTicks++;
            if (answer !== null || s.quizTicks >= 720) {
                if (answer !== null) s.answers.push({ tick: s.tick, choice: answer });
                const correct = answer === s.questions[s.quizIndex].correct;
                s.feedback = { index: s.quizIndex, correct, timeout: answer === null };
                s.feedbackTicks = 180;
                s.quiz = false; s.quizIndex++;
                if (!correct) { s.lives--; s.freeze = 90; if (s.lives <= 0) s.status = 'lost'; }
            }
            return;
        }
        if (jump) {
            s.jumps.push(s.tick);
            if (s.y === 0) s.velocity = 19;
        }
        if (s.velocity !== 0 || s.y > 0) {
            s.y = Math.max(0, s.y + s.velocity);
            s.velocity--;
            if (s.y === 0) s.velocity = 0;
        }
        if (s.invincible > 0) s.invincible--;
        if (s.freeze > 0) s.freeze--;
        else s.distance = Math.min(FINISH, s.distance + 9 + 2 * Math.floor(s.distance / 21000));
        while (s.next < s.obstacles.length && s.obstacles[s.next].at + s.obstacles[s.next].width < s.distance - 14) s.next++;
        const o = s.obstacles[s.next];
        if (o && !o.hit && s.invincible === 0 && o.at < s.distance + 14 && o.at + o.width > s.distance - 14 && s.y < o.height - 4) {
            o.hit = true; s.lives--; s.freeze = 90; s.invincible = 150;
            if (s.lives <= 0) s.status = 'lost';
        }
        if (s.status === 'running' && s.distance >= FINISH) s.status = 'won';
        if (s.status === 'running' && s.quizIndex < s.questions.length && s.distance >= s.questions[s.quizIndex].km * 1000) {
            s.quiz = true; s.quizTicks = 0; s.feedback = null;
        }
        if (s.tick >= MAX_TICKS && s.status === 'running') s.status = 'lost';
    }
    const api = { FINISH, HZ, MAX_TICKS, course, create, step };
    if (typeof module !== 'undefined') module.exports = api;
    else root.HknEngine = api;
})(typeof window !== 'undefined' ? window : globalThis);

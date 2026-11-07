// Run with node tests/hkn-engine.cjs. Produces a cross-language replay fixture.
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const root = path.resolve(__dirname, '..');
const context = { module: { exports: {} } };
vm.runInNewContext(fs.readFileSync(path.join(root, 'public/games/hkn62/engine.js'), 'utf8'), context);
const E = context.module.exports;
const questions = JSON.parse(fs.readFileSync(path.join(root, 'public/games/hkn62/trivia.json')));
const fixtures = [];
for (const seed of [6262026, 1, 2147483646, 1964, 2026]) {
    const s = E.create(seed, questions);
    while (s.status === 'running') {
        const o = s.obstacles[s.next];
        E.step(s, !s.quiz && !s.feedbackTicks && !!o && s.y === 0 && o.at - s.distance < 145 && o.at - s.distance > 0, s.quiz ? questions[s.quizIndex].correct : null);
    }
    assert.equal(s.status, 'won'); assert.equal(s.distance, 62000); assert.equal(s.lives, 3);
    const frozen = s.tick; E.step(s, true); assert.equal(s.tick, frozen);
    fixtures.push({ seed, ticks: s.tick, jumps: s.jumps, answers: s.answers });
    const lost = E.create(seed, questions);
    while (lost.status === 'running') E.step(lost);
    assert.equal(lost.status, 'lost'); assert.equal(lost.lives, 0);
}
fs.mkdirSync(path.join(__dirname, 'Fixtures'), { recursive: true });
fs.writeFileSync(path.join(__dirname, 'Fixtures/hkn-replays.json'), JSON.stringify(fixtures));
const php = process.env.HKN_TEST_PHP || 'php';
const result = spawnSync(php, ['-r', `require 'app/Services/Game/HknEngine.php'; $engine = new App\\Services\\Game\\HknEngine; foreach (json_decode(file_get_contents('tests/Fixtures/hkn-replays.json'),true) as $r) { if (!$engine->replay($r['seed'],$r['ticks'],$r['jumps'],$r['answers'])['won']) exit(1); if ($engine->replay($r['seed'],$r['ticks'],[],$r['answers'])['won']) exit(2); } echo 'PHP replay parity: OK';`], { cwd: root, encoding: 'utf8' });
if (result.error) throw result.error;
assert.equal(result.status, 0, result.stdout + result.stderr);
console.log(`5 courses: finish, collision, terminal state, deterministic PHP parity OK. Best: ${fixtures[0].ticks / 60}s. ${result.stdout}`);
for (const mode of ['wrong', 'timeout', 'all-timeout']) {
    const s = E.create(6262026, questions);
    while (s.status === 'running') {
        const o = s.obstacles[s.next];
        let answer = s.quiz ? questions[s.quizIndex].correct : null;
        if (s.quiz && (s.quizIndex === 0 || mode === 'all-timeout')) answer = mode === 'wrong' ? (answer + 1) % 3 : null;
        E.step(s, !s.quiz && !s.feedbackTicks && !!o && s.y === 0 && o.at-s.distance < 145 && o.at-s.distance > 0, answer);
    }
    assert.equal(s.status, mode === 'all-timeout' ? 'lost' : 'won');
    assert.equal(s.lives, mode === 'all-timeout' ? 0 : 2);
    const parity = spawnSync(php, ['-r', `require 'app/Services/Game/HknEngine.php'; $r=json_decode($argv[1],true); echo json_encode((new App\\Services\\Game\\HknEngine)->replay($r['seed'],$r['ticks'],$r['jumps'],$r['answers']));`, JSON.stringify({seed:s.seed,ticks:s.tick,jumps:s.jumps,answers:s.answers})], {cwd:root,encoding:'utf8'});
    assert.equal(parity.status,0,parity.stderr);
    assert.equal(JSON.parse(parity.stdout).won,s.status==='won');
}
console.log('Wrong answer, single timeout, and three timeouts: JS/PHP parity OK.');

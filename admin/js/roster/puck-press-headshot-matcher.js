/**
 * Matches roster players to image files by filename.
 *
 * Pure logic, no DOM or jQuery, so the bulk edit modal can run it against a
 * folder the browser has only listed (nothing is uploaded to match).
 */
(function(root) {
  'use strict';

  const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
  const NOISE = ['headshot', 'headshots', 'head', 'shot', 'photo', 'pic', 'picture', 'img', 'image', 'copy', 'scaled', 'final', 'edit', 'edited', 'web', 'crop', 'cropped', 'roster', 'player', 'hero', 'background', 'backgrounds', 'bg', 'banner', 'header', 'cover', 'action', 'wallpaper'];
  const SUFFIXES = ['jr', 'sr', 'ii', 'iii', 'iv'];

  const MATCHED_MIN   = 0.9;
  const CHECK_MIN     = 0.6;
  const AMBIGUOUS_GAP = 0.05;

  function isImageFile(filename) {
    const name = String(filename || '');
    if (name.charAt(0) === '.') return false;
    const dot = name.lastIndexOf('.');
    return dot > 0 && IMAGE_EXT.indexOf(name.slice(dot + 1).toLowerCase()) !== -1;
  }

  // Lowercase, accent-free tokens. Numbers are split out so a jersey number in
  // a filename can break ties without counting against the name comparison.
  function parse(text, dropWords) {
    const clean = String(text || '')
      .normalize('NFD').replace(/[̀-ͯ]/g, '')
      .toLowerCase()
      .replace(/['’`]/g, '')
      .replace(/([a-z])(\d)/g, '$1 $2').replace(/(\d)([a-z])/g, '$1 $2')
      .replace(/[^a-z0-9]+/g, ' ')
      .trim();
    const tokens  = [];
    const numbers = [];
    clean.split(' ').forEach(function(t) {
      if (!t) return;
      if (/^\d+$/.test(t)) { numbers.push(String(parseInt(t, 10))); return; }
      if (dropWords.indexOf(t) !== -1) return;
      tokens.push(t);
    });
    return { tokens: tokens, numbers: numbers, compact: tokens.join('') };
  }

  function parsePlayer(player) {
    const p = parse(player.name, SUFFIXES);
    p.first  = p.tokens[0] || '';
    p.last   = p.tokens.length > 1 ? p.tokens[p.tokens.length - 1] : '';
    p.number = /^\d+$/.test(String(player.number || '').trim()) ? String(parseInt(player.number, 10)) : '';
    return p;
  }

  function parseFile(filename) {
    const name = String(filename || '');
    const dot  = name.lastIndexOf('.');
    return parse(dot > 0 ? name.slice(0, dot) : name, NOISE.concat(SUFFIXES));
  }

  function levenshtein(a, b) {
    if (a === b) return 0;
    if (!a.length) return b.length;
    if (!b.length) return a.length;
    let prev = [];
    for (let j = 0; j <= b.length; j++) prev[j] = j;
    for (let i = 1; i <= a.length; i++) {
      const cur = [i];
      for (let j = 1; j <= b.length; j++) {
        cur[j] = Math.min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + (a.charAt(i - 1) === b.charAt(j - 1) ? 0 : 1));
      }
      prev = cur;
    }
    return prev[b.length];
  }

  function similarity(a, b) {
    const len = Math.max(a.length, b.length);
    return len ? 1 - levenshtein(a, b) / len : 0;
  }

  const hasAll = (needles, haystack) => needles.every(t => haystack.indexOf(t) !== -1);

  // 0..1 confidence that this file is this player's photo. `ctx` carries the
  // roster/folder-wide counts the weakest rules need to stay unambiguous.
  function score(p, f, ctx) {
    if (!p.tokens.length) return 0;

    // Number-only filename ("12.jpg"): usable only when the jersey is unique.
    if (!f.tokens.length) {
      return (p.number && f.numbers.length === 1 && f.numbers[0] === p.number && ctx.numberCount[p.number] === 1) ? 0.65 : 0;
    }

    let s = 0;
    const reversed = p.tokens.slice().reverse().join('');

    if (p.compact === f.compact) {
      s = 1;
    } else if (hasAll(p.tokens, f.tokens)) {
      s = 0.95;
    } else if (p.compact.length >= 5 && (f.compact.indexOf(p.compact) !== -1 || f.compact.indexOf(reversed) !== -1)) {
      s = 0.93;
    } else if (p.last && f.tokens.length >= 2 && hasAll(f.tokens, p.tokens) && f.tokens.indexOf(p.first) !== -1 && f.tokens.indexOf(p.last) !== -1) {
      s = 0.92; // Roster has a middle name the file omits.
    }

    if (s < MATCHED_MIN) {
      // Typos, in either name order.
      const sorted = arr => arr.slice().sort().join('');
      const sim = Math.max(similarity(p.compact, f.compact), similarity(reversed, f.compact), similarity(sorted(p.tokens), sorted(f.tokens)));
      s = Math.max(s, sim * 0.9);

      if (p.last) {
        const initial = p.first.charAt(0);
        const others  = f.tokens.filter(t => t !== p.last);
        if (f.tokens.indexOf(p.last) !== -1 && others.length) {
          // Short or long form of the first name ("Matt" / "Matthew").
          if (others.some(t => t.length >= 3 && (p.first.indexOf(t) === 0 || t.indexOf(p.first) === 0))) s = Math.max(s, 0.85);
          if (others.indexOf(initial) !== -1) s = Math.max(s, 0.8);
        }
        if (f.compact === initial + p.last || f.compact === p.last + initial) s = Math.max(s, 0.8);
        if (f.compact === p.last && ctx.lastCount[p.last] === 1 && ctx.fileCompactCount[f.compact] === 1) s = Math.max(s, 0.7);
      }
    }

    // Uncapped so a jersey number still separates two players with the same name.
    if (s > 0 && p.number && f.numbers.indexOf(p.number) !== -1) s += 0.05;
    return s;
  }

  /**
   * @param {Array<{id: string, name: string, number: string}>} players
   * @param {Array<string>} filenames  Image filenames (basename only).
   * @returns {{assignments: Object, unused: number[]}} assignments is keyed by
   *   player id: { file: index|-1, score: number, status: 'matched'|'check'|'none' }.
   */
  function match(players, filenames) {
    const P = players.map(parsePlayer);
    const F = filenames.map(parseFile);

    const ctx = { lastCount: {}, numberCount: {}, fileCompactCount: {}, nameCount: {} };
    P.forEach(function(p) {
      ctx.nameCount[p.compact] = (ctx.nameCount[p.compact] || 0) + 1;
      if (p.last)   ctx.lastCount[p.last]     = (ctx.lastCount[p.last] || 0) + 1;
      if (p.number) ctx.numberCount[p.number] = (ctx.numberCount[p.number] || 0) + 1;
    });
    F.forEach(function(f) { ctx.fileCompactCount[f.compact] = (ctx.fileCompactCount[f.compact] || 0) + 1; });

    const scores = P.map(p => F.map(f => score(p, f, ctx)));
    const pairs  = [];
    scores.forEach(function(row, pi) {
      row.forEach(function(s, fi) { if (s >= CHECK_MIN) pairs.push({ pi: pi, fi: fi, s: s }); });
    });
    pairs.sort((a, b) => b.s - a.s);

    // Best pairs first, each image used once, so two players with similar
    // names can't both claim the same file.
    const playerFile = P.map(() => -1);
    const fileTaken  = F.map(() => false);
    pairs.forEach(function(pair) {
      if (playerFile[pair.pi] !== -1 || fileTaken[pair.fi]) return;
      playerFile[pair.pi] = pair.fi;
      fileTaken[pair.fi]  = true;
    });

    const assignments = {};
    players.forEach(function(player, pi) {
      const fi = playerFile[pi];
      if (fi === -1) { assignments[player.id] = { file: -1, score: 0, status: 'none' }; return; }
      const s = scores[pi][fi];
      // Another free image is nearly as good a fit: let a human pick.
      const rival = scores[pi].some((other, oi) => oi !== fi && !fileTaken[oi] && other >= CHECK_MIN && s - other <= AMBIGUOUS_GAP);
      // Same name as a teammate: only a jersey number in the filename says whose photo it is.
      const twin = ctx.nameCount[P[pi].compact] > 1 && !(P[pi].number && F[fi].numbers.indexOf(P[pi].number) !== -1);
      assignments[player.id] = { file: fi, score: s, status: (s >= MATCHED_MIN && !rival && !twin) ? 'matched' : 'check' };
    });

    const unused = [];
    fileTaken.forEach(function(taken, fi) { if (!taken) unused.push(fi); });

    return { assignments: assignments, unused: unused };
  }

  const api = { match: match, isImageFile: isImageFile };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else root.ppHeadshotMatcher = api;
})(typeof window !== 'undefined' ? window : this);

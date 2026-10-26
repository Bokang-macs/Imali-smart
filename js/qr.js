/**
 * Imali Smart - small offline QR code generator.
 * Draws a QR code in the browser, so statements never depend on an outside QR service.
 * Byte mode, error correction level M, versions 1 to 20 (enough for links up to ~600 characters).
 *
 * Use: <div data-qr="https://example.com/statement.php?t=abc"></div>  (filled in automatically)
 * or:  ImaliQR.svg('text', { size: 160, margin: 2 })
 */
(function (root) {
    'use strict';

    var ECC_PER_BLOCK_M = [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26];
    var NUM_BLOCKS_M = [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16];
    var MAX_VERSION = 20;

    function rawModules(ver) {
        var r = (16 * ver + 128) * ver + 64;
        if (ver >= 2) {
            var n = Math.floor(ver / 7) + 2;
            r -= (25 * n - 10) * n - 55;
            if (ver >= 7) r -= 36;
        }
        return r;
    }
    function dataCodewords(ver) {
        return Math.floor(rawModules(ver) / 8) - ECC_PER_BLOCK_M[ver] * NUM_BLOCKS_M[ver];
    }

    function utf8Bytes(text) {
        var s = unescape(encodeURIComponent(text)), out = [];
        for (var i = 0; i < s.length; i++) out.push(s.charCodeAt(i));
        return out;
    }

    // Reed-Solomon over GF(256).
    function gfMul(x, y) {
        var z = 0;
        for (var i = 7; i >= 0; i--) {
            z = (z << 1) ^ ((z >>> 7) * 0x11D);
            z ^= ((y >>> i) & 1) * x;
        }
        return z;
    }
    function rsDivisor(degree) {
        var result = [], i, j;
        for (i = 0; i < degree - 1; i++) result.push(0);
        result.push(1);
        var root = 1;
        for (i = 0; i < degree; i++) {
            for (j = 0; j < result.length; j++) {
                result[j] = gfMul(result[j], root);
                if (j + 1 < result.length) result[j] ^= result[j + 1];
            }
            root = gfMul(root, 0x02);
        }
        return result;
    }
    function rsRemainder(data, divisor) {
        var result = divisor.map(function () { return 0; });
        data.forEach(function (b) {
            var factor = b ^ result.shift();
            result.push(0);
            divisor.forEach(function (coef, i) { result[i] ^= gfMul(coef, factor); });
        });
        return result;
    }

    function buildCodewords(bytes, ver) {
        var bits = [];
        function put(val, len) { for (var i = len - 1; i >= 0; i--) bits.push((val >>> i) & 1); }
        put(4, 4);
        put(bytes.length, ver <= 9 ? 8 : 16);
        bytes.forEach(function (b) { put(b, 8); });
        var cap = dataCodewords(ver) * 8;
        put(0, Math.min(4, cap - bits.length));
        while (bits.length % 8) bits.push(0);
        for (var pad = 0xEC; bits.length < cap; pad ^= 0xEC ^ 0x11) put(pad, 8);
        var data = [];
        for (var i = 0; i < bits.length; i += 8) {
            var v = 0;
            for (var k = 0; k < 8; k++) v = (v << 1) | bits[i + k];
            data.push(v);
        }

        var nBlocks = NUM_BLOCKS_M[ver], eccLen = ECC_PER_BLOCK_M[ver];
        var raw = Math.floor(rawModules(ver) / 8);
        var nShort = nBlocks - (raw % nBlocks);
        var shortLen = Math.floor(raw / nBlocks);
        var blocks = [], div = rsDivisor(eccLen), pos = 0;
        for (var b = 0; b < nBlocks; b++) {
            var dat = data.slice(pos, pos + shortLen - eccLen + (b < nShort ? 0 : 1));
            pos += dat.length;
            var ecc = rsRemainder(dat, div);
            if (b < nShort) dat.push(0);
            blocks.push(dat.concat(ecc));
        }
        var out = [];
        for (var x = 0; x < blocks[0].length; x++) {
            for (var j = 0; j < blocks.length; j++) {
                if (x !== shortLen - eccLen || j >= nShort) out.push(blocks[j][x]);
            }
        }
        return out;
    }

    function alignPositions(ver) {
        if (ver === 1) return [];
        var n = Math.floor(ver / 7) + 2;
        var size = ver * 4 + 17;
        var step = (ver === 32) ? 26 : Math.ceil((ver * 4 + 4) / (n * 2 - 2)) * 2;
        var res = [6];
        for (var p = size - 7; res.length < n; p -= step) res.splice(1, 0, p);
        return res;
    }

    function makeMatrix(codewords, ver, mask) {
        var size = ver * 4 + 17, m = [], fn = [], y, x;
        for (y = 0; y < size; y++) { m.push(new Array(size).fill(false)); fn.push(new Array(size).fill(false)); }
        function set(px, py, dark) { m[py][px] = dark; fn[py][px] = true; }

        for (var i = 0; i < size; i++) { set(6, i, i % 2 === 0); set(i, 6, i % 2 === 0); }
        function finder(cx, cy) {
            for (var dy = -4; dy <= 4; dy++) for (var dx = -4; dx <= 4; dx++) {
                var d = Math.max(Math.abs(dx), Math.abs(dy)), xx = cx + dx, yy = cy + dy;
                if (xx >= 0 && xx < size && yy >= 0 && yy < size) set(xx, yy, d !== 2 && d !== 4);
            }
        }
        finder(3, 3); finder(size - 4, 3); finder(3, size - 4);
        var ap = alignPositions(ver);
        for (var a = 0; a < ap.length; a++) for (var b = 0; b < ap.length; b++) {
            if ((a === 0 && b === 0) || (a === 0 && b === ap.length - 1) || (a === ap.length - 1 && b === 0)) continue;
            for (var dy = -2; dy <= 2; dy++) for (var dx = -2; dx <= 2; dx++) set(ap[a] + dx, ap[b] + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1);
        }
        function bit(v, k) { return ((v >>> k) & 1) !== 0; }
        function formatBits(msk) {
            var data = (0 << 3) | msk, rem = data; // error correction level M = 0
            for (var k = 0; k < 10; k++) rem = (rem << 1) ^ ((rem >>> 9) * 0x537);
            var bits = ((data << 10) | rem) ^ 0x5412, k2;
            for (k2 = 0; k2 <= 5; k2++) set(8, k2, bit(bits, k2));
            set(8, 7, bit(bits, 6)); set(8, 8, bit(bits, 7)); set(7, 8, bit(bits, 8));
            for (k2 = 9; k2 < 15; k2++) set(14 - k2, 8, bit(bits, k2));
            for (k2 = 0; k2 < 8; k2++) set(size - 1 - k2, 8, bit(bits, k2));
            for (k2 = 8; k2 < 15; k2++) set(8, size - 15 + k2, bit(bits, k2));
            set(8, size - 8, true);
        }
        formatBits(0);
        if (ver >= 7) {
            var rem = ver;
            for (var r = 0; r < 12; r++) rem = (rem << 1) ^ ((rem >>> 11) * 0x1F25);
            var vb = (ver << 12) | rem;
            for (var k = 0; k < 18; k++) {
                var aa = size - 11 + (k % 3), bb = Math.floor(k / 3);
                set(aa, bb, bit(vb, k)); set(bb, aa, bit(vb, k));
            }
        }
        // Place data bits in the zig-zag order.
        var idx = 0;
        for (var right = size - 1; right >= 1; right -= 2) {
            if (right === 6) right = 5;
            for (var vert = 0; vert < size; vert++) for (var j = 0; j < 2; j++) {
                var xx = right - j, up = ((right + 1) & 2) === 0, yy = up ? size - 1 - vert : vert;
                if (!fn[yy][xx] && idx < codewords.length * 8) {
                    m[yy][xx] = ((codewords[idx >>> 3] >>> (7 - (idx & 7))) & 1) !== 0;
                    idx++;
                }
            }
        }
        // Apply the mask and write the real format bits.
        var test = [
            function (px, py) { return (px + py) % 2 === 0; },
            function (px, py) { return py % 2 === 0; },
            function (px) { return px % 3 === 0; },
            function (px, py) { return (px + py) % 3 === 0; },
            function (px, py) { return (Math.floor(px / 3) + Math.floor(py / 2)) % 2 === 0; },
            function (px, py) { return px * py % 2 + px * py % 3 === 0; },
            function (px, py) { return (px * py % 2 + px * py % 3) % 2 === 0; },
            function (px, py) { return ((px + py) % 2 + px * py % 3) % 2 === 0; }
        ][mask];
        for (y = 0; y < size; y++) for (x = 0; x < size; x++) if (!fn[y][x] && test(x, y)) m[y][x] = !m[y][x];
        formatBits(mask);
        return m;
    }

    // A simplified penalty score (long runs, 2x2 blocks, dark/light balance) to pick a clear mask.
    function penalty(m) {
        var size = m.length, score = 0, x, y, dark = 0;
        for (y = 0; y < size; y++) {
            var runX = 1, runY = 1;
            for (x = 0; x < size; x++) {
                if (m[y][x]) dark++;
                if (x > 0) { if (m[y][x] === m[y][x - 1]) { runX++; if (runX === 5) score += 3; else if (runX > 5) score++; } else runX = 1; }
                if (x > 0) { if (m[x][y] === m[x - 1][y]) { runY++; if (runY === 5) score += 3; else if (runY > 5) score++; } else runY = 1; }
                if (x > 0 && y > 0 && m[y][x] === m[y][x - 1] && m[y][x] === m[y - 1][x] && m[y][x] === m[y - 1][x - 1]) score += 3;
            }
        }
        var total = size * size;
        score += Math.floor(Math.abs(dark * 20 - total * 10) / total) * 10;
        return score;
    }

    function encode(text) {
        var bytes = utf8Bytes(String(text));
        for (var ver = 1; ver <= MAX_VERSION; ver++) {
            var used = 4 + (ver <= 9 ? 8 : 16) + bytes.length * 8;
            if (used <= dataCodewords(ver) * 8) {
                var cw = buildCodewords(bytes, ver), best = null, bestScore = 1e9;
                for (var mask = 0; mask < 8; mask++) {
                    var mat = makeMatrix(cw, ver, mask), sc = penalty(mat);
                    if (sc < bestScore) { bestScore = sc; best = mat; }
                }
                return best;
            }
        }
        throw new Error('Link is too long for a QR code.');
    }

    function svg(text, opts) {
        opts = opts || {};
        var m = encode(text), n = m.length, margin = opts.margin === undefined ? 2 : opts.margin;
        var dim = n + margin * 2, d = '';
        for (var y = 0; y < n; y++) for (var x = 0; x < n; x++) if (m[y][x]) d += 'M' + (x + margin) + ',' + (y + margin) + 'h1v1h-1z';
        var size = opts.size || 160;
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + dim + ' ' + dim + '" width="' + size + '" height="' + size + '" shape-rendering="crispEdges" role="img" aria-label="' + (opts.label || 'QR code') + '">' +
            '<rect width="' + dim + '" height="' + dim + '" fill="#fff"/><path d="' + d + '" fill="#000"/></svg>';
    }

    function renderAll() {
        var nodes = document.querySelectorAll('[data-qr]');
        for (var i = 0; i < nodes.length; i++) {
            var el = nodes[i];
            try {
                el.innerHTML = svg(el.getAttribute('data-qr') || '', {
                    size: parseInt(el.getAttribute('data-qr-size') || '96', 10),
                    label: el.getAttribute('data-qr-label') || 'QR code to open this statement'
                });
            } catch (e) { el.textContent = el.getAttribute('data-qr') || ''; }
        }
    }

    root.ImaliQR = { encode: encode, svg: svg, renderAll: renderAll };
    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', renderAll); else renderAll();
    }
})(typeof window !== 'undefined' ? window : globalThis);

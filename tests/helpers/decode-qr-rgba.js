'use strict';

const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

function fail(code) {
    process.exitCode = code;
}

if (process.argv.length !== 5) {
    fail(2);
} else {
    try {
        const rgbaPath = process.argv[2];
        const metadataPath = process.argv[3];
        const decoderPath = process.argv[4];
        const metadata = JSON.parse(fs.readFileSync(metadataPath, 'utf8'));
        const width = Number(metadata.width);
        const height = Number(metadata.height);
        if (!Number.isInteger(width) || !Number.isInteger(height)
            || width <= 0 || height <= 0 || width > 4096 || height > 4096) {
            fail(2);
        } else {
            const rgba = fs.readFileSync(rgbaPath);
            if (rgba.length !== width * height * 4) {
                fail(2);
            } else {
                const imported = require(path.resolve(decoderPath));
                const decode = typeof imported === 'function' ? imported : imported.default;
                if (typeof decode !== 'function') {
                    fail(2);
                } else {
                    const pixels = new Uint8ClampedArray(
                        rgba.buffer,
                        rgba.byteOffset,
                        rgba.byteLength,
                    );
                    const result = decode(pixels, width, height, {inversionAttempts: 'dontInvert'});
                    if (!result || typeof result.data !== 'string' || result.data === '') {
                        fail(1);
                    } else {
                        process.stdout.write(crypto.createHash('sha256').update(result.data).digest('hex'));
                    }
                }
            }
        }
    } catch (error) {
        fail(1);
    }
}

import { spawn } from 'node:child_process';
import { createHash } from 'node:crypto';
import { open, readFile, rm, stat } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';

const projectPath = resolve();
const lockPath = join(
    tmpdir(),
    `wayfinder-${createHash('sha256').update(projectPath).digest('hex')}.lock`,
);

function isProcessRunning(processId) {
    try {
        process.kill(processId, 0);

        return true;
    } catch (error) {
        return error.code === 'EPERM';
    }
}

async function acquireLock() {
    while (true) {
        try {
            const lock = await open(lockPath, 'wx');
            await lock.writeFile(`${process.pid}\n`);

            return lock;
        } catch (error) {

            if (error.code !== 'EEXIST') {
                throw error;
            }

            const processId = Number(
                (await readFile(lockPath, 'utf8').catch(() => '')).trim(),
            );

            if (processId && !isProcessRunning(processId)) {
                await rm(lockPath, { force: true });
                continue;
            }

            const lockStats = await stat(lockPath).catch(() => null);

            if (!processId && lockStats && Date.now() - lockStats.mtimeMs > 30_000) {
                await rm(lockPath, { force: true });
                continue;
            }

            await delay(100);
        }
    }
}

const lock = await acquireLock();

try {
    const exitCode = await new Promise((resolveExit, reject) => {
        const child = spawn(
            'php',
            ['artisan', 'wayfinder:generate', ...process.argv.slice(2)],
            { stdio: 'inherit' },
        );

        child.on('error', reject);

        child.on('exit', (code, signal) => {
            if (code === 0) {
                resolveExit(0);

                return;
            }

            reject(new Error(`Wayfinder exited with ${signal ?? code}.`));
        });
    });

    process.exitCode = exitCode;
} finally {
    await lock.close();
    await rm(lockPath, { force: true });
}
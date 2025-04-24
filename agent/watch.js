import chokidar from 'chokidar';
import { execSync } from "node:child_process";

const watching = chokidar.watch('./src', {
    ignored: (path, stats) => stats?.isFile() && !path.endsWith('.php'),
    ignoreInitial: true,
})

watching.on('all', (event, path) => {
    console.log(`${event}: ${path}`)
    console.log('> composer build')
    console.log(execSync('composer build').toString('utf8'))
})

console.log('> composer build')
console.log(execSync('composer build').toString('utf8'))

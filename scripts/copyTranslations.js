#!/usr/bin/env node
/* eslint-disable import/no-extraneous-dependencies, no-console */
const path = require('path');
const fs = require('fs');
const chalk = require('chalk');
const globSync = require('glob').sync;
const mkdirpSync = require('mkdirp').sync;

const intlPath = path.join(path.dirname(require.resolve('panneau')), '../intl/lang');
const files = globSync(path.join(intlPath, '*'));

const getContent = messages => (`
<?php

return [
${Object.keys(messages).map(key => (`    '${key}' => '${messages[key]}',`)).join('\n')}
];
`);

console.log(chalk.yellow('Copying translations...'));
files.forEach((file) => {
    const lang = path.basename(file, '.json');
    const messages = require(file); // eslint-disable-line global-require, import/no-dynamic-require
    const langPath = path.join(__dirname, `../src/resources/lang/${lang}/`);
    mkdirpSync(langPath);
    const messagesByFiles = Object.keys(messages).reduce((allMessages, key) => {
        const keyParts = key.split('.');
        const fileKey = keyParts.shift();
        return {
            ...allMessages,
            [fileKey]: {
                ...(allMessages[fileKey] || null),
                [keyParts.join('.')]: messages[key],
            },
        };
    }, {});
    console.log(chalk.yellow(`Copying "${lang}" translations...`));
    Object.keys(messagesByFiles).forEach((fileName) => {
        const content = getContent(messagesByFiles[fileName]);
        const langFilePath = path.join(langPath, `${fileName}.php`);
        fs.writeFileSync(langFilePath, content);
        console.log(`${chalk.green('✔')} ${langFilePath} ${chalk.yellow('<')} ${Object.keys(messagesByFiles[fileName]).length} translation(s)`);
    });
});
console.log(chalk.green('Translations copied.'));

import path from 'path';
import fs from 'fs';
import { sync as globSync } from 'glob';
import { sync as mkdirpSync } from 'mkdirp';

const intlPath = path.join(path.dirname(require.resolve('panneau')), '../intl/lang');
const files = globSync(path.join(intlPath, '*'));

const getContent = (messages) => (`
<?php

return [
${Object.keys(messages).map((key) => (`    '${key}' => '${messages[key]}',`)).join('\n')}
];
`);

files.forEach((file) => {
    const lang = path.basename(file, '.json');
    const messages = require(file);
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
    Object.keys(messagesByFiles).forEach((fileName) => {
        const content = getContent(messagesByFiles[fileName]);
        fs.writeFileSync(path.join(langPath, `${fileName}.php`), content, )
    });
});

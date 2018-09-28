/* globals panneau_config */
import domready from 'domready';
import panneau from 'panneau';

import '../styles/main.global.scss';

domready(() => {
    const el = document.getElementById('panneau');
    if (el === null) {
        return;
    }
    const {
        definition, user, locale, messages, ...opts
    } = panneau_config();
    panneau()
        .setLocale(locale)
        .setDefinition(definition)
        .setUser(user)
        .setMessages(messages)
        .setOptions(opts)
        .render(el);
});

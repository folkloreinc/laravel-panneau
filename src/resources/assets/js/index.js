/* globals Panneau */
import domready from 'domready';
import FieldsGroup from '@panneau/fields-group';

domready(() => {
    const panneau = new Panneau(window.panneau_definition, {
        locale: 'fr',
    });
    panneau.components('fields.group', FieldsGroup);

    const target = document.getElementById('panneau');
    panneau.render(target);
});

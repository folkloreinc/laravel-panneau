/* globals Panneau */
import domready from 'domready';
import FieldsGroup from '@panneau/fields-group';

domready(() => {
    const panneau = new Panneau(window.panneau_definition, {
        locale: 'fr',
    });
    panneau.componentsCollection.addComponent('fields.group', FieldsGroup);

    const target = window.document.getElementById('panneau');
    panneau.render(target);
});

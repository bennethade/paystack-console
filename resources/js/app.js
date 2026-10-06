import Alpine from 'alpinejs';
import appShell from './explorer/shell';
import contextSwitcher from './explorer/context';
import endpointConsole from './explorer/console';
import { copyButton, jsonBlock, signatureVerifier } from './explorer/webhooks';

Alpine.data('appShell', appShell);
Alpine.data('contextSwitcher', contextSwitcher);
Alpine.data('endpointConsole', endpointConsole);
Alpine.data('signatureVerifier', signatureVerifier);
Alpine.data('jsonBlock', jsonBlock);
Alpine.data('copyButton', copyButton);

window.Alpine = Alpine;
Alpine.start();

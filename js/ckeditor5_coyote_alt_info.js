(function (Drupal, settings, once, $) {

  'use strict';

  window.addEventListener('load', () => {
    if (!('MutationObserver') in window) {
      console.debug('MutationObserver unavailable!');
      return;
    }

    console.info('[Coyote] Hooking into CKEditor5');

    let lastClickedImage = null;

    const processWrapperMutation = mutation => {
      if (mutation.type !== 'childList') {
        return;
      }

      const selector = 'form.ck-text-alternative-form';
      mutation.addedNodes
        .forEach(node => {
          if (
              node.nodeType !== Node.ELEMENT_NODE ||
              !node.matches(selector) ||
              node.dataset.coyoteHooked
          ) {
            return;
          }

          const input = node.querySelector('.ck-input-text');
          input.setAttribute('readonly', true);

          if (lastClickedImage === null) {
            console.warn('Could not locate alt dialog trigger image');
            return;
          }

          $.ajax(`${document.location.protocol}//${document.location.host}/coyote/get_info`, {
            data: { url : lastClickedImage.src }
          }).done(data => {
            input.value = data.alt;
            node.insertAdjacentHTML(
              "beforeend",
              `<div class="coyote-info-message">The alternative text is managed by ${data.link}.</div>`
            );

            node.dataset.coyoteHooked = "true";
          })
        });
    }

    const processImageMutation = mutation => {
      if (mutation.type !== 'childList') {
        return;
      }

      const selector = '.image-inline, .ck-widget';
      mutation.addedNodes
        .forEach(node => {
          if (node.nodeType !== Node.ELEMENT_NODE || !node.matches(selector)) {
            return;
          }

          const img = node.querySelector('img');

          if (img) {
            img.addEventListener('click', function () {
              lastClickedImage = this;
            })
          }
        });
    }

    const applyToMutations = f => mutations => mutations.forEach(f);
    const trackMutations = (selector, f) => {
      const e = document.querySelector(selector);
      if (!e) {
        return;
      }

      (new MutationObserver(applyToMutations(f)).observe(e, {subtree: true, childList: true}));
    }

    trackMutations('.ck-body-wrapper', processWrapperMutation);
    trackMutations('.ck, .ck-content', processImageMutation);
  })

}(Drupal, drupalSettings, once, jQuery));
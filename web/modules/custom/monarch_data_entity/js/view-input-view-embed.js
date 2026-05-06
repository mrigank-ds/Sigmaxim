(function ($) {
  const iFrame = window.frameElement;
  const parentDoc = iFrame ? iFrame.ownerDocument : document;
  const $iFrame = $(iFrame);
  const inputElement = iFrame ? $iFrame.parent().nextAll('input').first()[0] : null;
  const totalElement = iFrame ? $iFrame.parent().nextAll('.view-widget-selection-text').first()[0] : null;
  
  let cardinality = iFrame ? $iFrame.data('cardinality') : 1;
  if (!cardinality || cardinality <= 0) {
    cardinality = Infinity;
  }
  
  let values = {};
  let inputs = {};

  iFrame.parentElement.style.paddingTop = Math.max(window.document.body.parentElement.scrollHeight, window.document.body.scrollHeight) + 'px';

  window.addEventListener('DOMContentLoaded', function(e) {
    iFrame.parentElement.style.paddingTop = Math.max(window.document.body.parentElement.scrollHeight, window.document.body.scrollHeight) + 'px';
  });

  function updateTotal() {
    let count = Object.values(values).filter(Boolean).length;
    //console.log('UpdateTotal: count =', count, 'values =', values);
    if (totalElement) {
      totalElement.innerHTML = `<em>${count} Selected</em>`;
    }
  }

  function pullValues() {
    //console.log('PullValues: inputElement.value =', inputElement ? inputElement.value : 'NULL');
    if (!inputElement) return;
    
    Object.keys(values).forEach(k => {
      values[k] = false;
    });

    inputElement.value.split(',').filter(Boolean).map(v => String(v).trim()).forEach(k => {
      values[k] = true;
    });

    Object.keys(values).forEach(k => {
      if (inputs[k]) {
        inputs[k].checked = Boolean(values[k]);
      }
    });

    updateTotal();
  }

  function setValue(id, v = true) {
    v = Boolean(v);
    //console.log('SetValue: id =', id, 'v =', v, 'current values =', values, 'cardinality =', cardinality);

    if (values[id] != v) {
      if (v && Object.values(values).filter(Boolean).length >= cardinality) {
        if (cardinality !== 1) {
          if (inputs[id]) inputs[id].checked = false;
          return;
        }

        values = {};

        Object.keys(inputs).forEach(k => {
          if (k != id) {
            inputs[k].checked = false;
          }
        });
      }

      values[id] = v;

      if (inputElement) {
        inputElement.value = Object.keys(values).filter(v => values[v]).join(',') || '';
        //console.log('Updated hidden input value:', inputElement.value);
        // Dispatch event so parent can hear it.
        inputElement.dispatchEvent(new Event('change', { bubbles: true }));
      }
      updateTotal();
    }
  }

  Drupal.behaviors.viewInputViewEmbed = {
    attach: function (context) {
      once('view-input-view-embed', 'input.view-input-view-selector[type="checkbox"]', context).forEach(function (element) {
        let id = String(element.value);

        if (!id) {
          return;
        }

        inputs[id] = element;

        element.addEventListener('click', function (e) {
          if (id) {
            setValue(id, element.checked);
          }
        });
      });

      pullValues();
    },
  };
})(jQuery);

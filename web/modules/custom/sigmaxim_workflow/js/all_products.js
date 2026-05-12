(function ($, Drupal, drupalSettings) {
  /**
   * Attaches the JS for all products block
   */
  $(document).ready(function () {
    var requestOptions = {
      method: "GET",
      redirect: "follow",
    };
    let selectedElem = null;
    let productsList = [];

    fetch("/api/v1/all-products", requestOptions)
      .then((response) => response.json())
      .then((result) => {
        productsList = result;
        productsList && productsList.length ? findProducts() : null;
      })
      .catch((error) => console.log("error", error));

    function findProducts() {
      if (selectedElem && selectedElem != "null") {
        let parentElem = productsList.filter((elm) => elm.tid == selectedElem);

        if (parentElem && parentElem.length) {
          if (parentElem[0].url === "") {
            showProducts(
              productsList.filter((elm) => elm.pid == parentElem[0].tid)
            );
           event.preventDefault();

          } else {
            window.location.href = window.location.origin + parentElem[0].url;
          }
        }
      } else {
        showProducts(productsList.filter((elm) => elm.pid == "0"));
      }
    }

    function showProducts(list) {
      if (list && list.length) {
        var str = '<h2 class="all-products-title">Products</h2><ul id="sigmaxim-workflow-product-table">';
        for (var i in list) {
          $data1="${list[i].url}";
          if ($data1 == "null") {
            str += `<li class="item-list-${i} cell" key="${list[i].tid}" data="${list[i].url}"><a>${list[i].name}<img class="product-image"src="${list[i].img}"/></a></li>`;
          }
          else {
          str += `<li class="item-list-${i} cell" key="${list[i].tid}" data="${list[i].url}"><a href="${list[i].url}">${list[i].name}<img class="product-image xyz12"src="${list[i].img}"/></a></li>`;
          }
        }
        $(".sigmaxim-workflow-product-table-wrapper").html("").append(str);
        if (selectedElem && selectedElem != "null") {
          $("#sigmaxim-workflow-product-table").prepend(
            `<input type="button" class="button" id="btnAdd" value="Go Back"><br>`
          );
          $("#sigmaxim-workflow-product-table").prepend(
            `<input type="button" class="button button--primary" id="reset" value="Reset">`
          );
        }

        bindHandlers();
      }
    }

    function bindHandlers() {
      $("#sigmaxim-workflow-product-table > li")
        .unbind("click")
        .click(function () {
          selectedElem = $(this).attr("key");
          findProducts();
          if ($(this).attr("data") == "") {
            findProducts();
          } else {
            findProducts();
          }
        });

      $("#btnAdd")
        .unbind("click")
        .click(function (e) {
          e.preventDefault();
          findParents();
        });

      $("#reset")
        .unbind("click")
        .click(function (e) {
          e.preventDefault();
          selectedElem = null;
          showProducts(productsList.filter((elm) => elm.pid == "0"));
        });
    }

    function findParents() {
      if (selectedElem && selectedElem != "null") {
        let elem = productsList.filter((elm) => elm.tid == selectedElem);
        if (elem && elem.length) {
          if (elem[0].pid == "0") {
            selectedElem = null;
            showProducts(productsList.filter((elm) => elm.pid == "0"));
          } else {
            let parentElem = productsList.filter(
              (elm) => elm.tid == elem[0].pid
            );
            if (parentElem && parentElem.length) {
              selectedElem = parentElem[0].tid;
              findProducts();
            }
          }
        }
      } else {
        showProducts(productsList.filter((elm) => elm.pid == "0"));
      }
    }
  });
  function callFunc() {
    let text = $(".field--type-expression-integer")
      .find("div.description")
      .text()
      .trim();
    var results = [],
      m;
    var regex = /\[([^\][]*)]/g;

    while ((m = regex.exec(text))) {
      results.push(m[1].substring(m[1].indexOf(":") + 1));
    }
    console.log(results);
    let result = callFunc(results);
    console.log(result, text);
    result.forEach((elm) => {
      for (var i in elm) {
        text = text.replace(`[sigmaxim_workflow_order:${i}]`, elm[i]);
        console.log(text);
      }
      $(".field--type-expression-integer").find("input").val(addbits(text));
    });

    function callFunc(results) {
      let arr = [];
      results.forEach((elm) => {
        console.log(`${elm.replace(/_/g, "-")}`);
        arr.push({
          [elm]: $(`#edit-${elm.replace(/_/g, "-")}-0-value`).val()
        });
      });
      return arr;
    }

    function addbits(s) {
      if (s.length) {
        return (s.replace(/\s/g, "").match(/[+\-]?([0-9\.]+)/g) || []).length ?
          (s.replace(/\s/g, "").match(/[+\-]?([0-9\.]+)/g) || []).reduce(
            function(sum, value) {
              return parseFloat(sum) + parseFloat(value);
            }
          ) :
          0;
      } else {
        return 0;
      }
    }
  }



  //
  Drupal.behaviors.resultsLinkEmptyCheck = {
    attach: function (context, settings) {
      $('a[href^="/results/files/"]', context).once('check-folder-empty').each(function () {
        const $link = $(this);
        const folderId = $link.attr('href').split('/').pop();

        $.ajax({
          url: '/check/folder/' + folderId,
          method: 'GET',
          success: function (data) {
            if (data.empty) {
              $link.attr('data-folder-empty', '1');
              $link.hide(); // optionally hide
            } else {
              $link.attr('data-folder-empty', '0');
            }
          }
        });
      });
    }
  };

  //console.log('here');
  $('input').on('input', function() {
    callFunc();
  });
})(jQuery, Drupal, drupalSettings);

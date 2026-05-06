(function ($, Drupal, drupalSettings) {
    /**
     * Attaches the JS for all products block
     */

    // Function to check window size and hide/show orders table columns
    function adjustTableColumns() {
        var windowWidth = $(window).width();

        // All-orders table
        $('.all-orders table.views-table th:nth-child(8), .all-orders table.views-table td:nth-child(8)').hide();
        if (windowWidth <= 480) {
            $('.all-orders table.views-table th:nth-child(4), .all-orders table.views-table td:nth-child(4)').hide();
            $('.all-orders table.views-table th:nth-child(5), .all-orders table.views-table td:nth-child(5)').hide();
            $('.all-orders table.views-table th:nth-child(6), .all-orders table.views-table td:nth-child(6)').hide();
            $('.all-orders table.views-table th:nth-child(7), .all-orders table.views-table td:nth-child(7)').hide();
            $('.all-orders table.views-table th:nth-child(8), .all-orders table.views-table td:nth-child(8)').show();
        } else if (windowWidth <= 768) {
            $('.all-orders table.views-table th:nth-child(4), .all-orders table.views-table td:nth-child(4)').show();
            $('.all-orders table.views-table th:nth-child(5), .all-orders table.views-table td:nth-child(5)').hide();
            $('.all-orders table.views-table th:nth-child(6), .all-orders table.views-table td:nth-child(6)').hide();
            $('.all-orders table.views-table th:nth-child(7), .all-orders table.views-table td:nth-child(7)').hide();
            $('.all-orders table.views-table th:nth-child(8), .all-orders table.views-table td:nth-child(8)').show();
        } else if (windowWidth <= 1024) {
            $('.all-orders table.views-table th:nth-child(4), .all-orders table.views-table td:nth-child(4)').show();
            $('.all-orders table.views-table th:nth-child(5), .all-orders table.views-table td:nth-child(5)').show();
            $('.all-orders table.views-table th:nth-child(6), .all-orders table.views-table td:nth-child(6)').show();
            $('.all-orders table.views-table th:nth-child(7), .all-orders table.views-table td:nth-child(7)').hide();
            $('.all-orders table.views-table th:nth-child(8), .all-orders table.views-table td:nth-child(8)').show();
        } else {
            $('.all-orders table.views-table th:nth-child(4), .all-orders table.views-table td:nth-child(4)').show();
            $('.all-orders table.views-table th:nth-child(5), .all-orders table.views-table td:nth-child(5)').show();
            $('.all-orders table.views-table th:nth-child(6), .all-orders table.views-table td:nth-child(6)').show();
            $('.all-orders table.views-table th:nth-child(7), .all-orders table.views-table td:nth-child(7)').show();
            $('.all-orders table.views-table th:nth-child(8), .all-orders table.views-table td:nth-child(8)').hide();
        }

        // My-orders table
        $('.my-orders table.views-table th:nth-child(7), .my-orders table.views-table td:nth-child(7)').hide();
        if (windowWidth <= 480) {
            $('.my-orders table.views-table th:nth-child(3), .my-orders table.views-table td:nth-child(3)').hide();
            $('.my-orders table.views-table th:nth-child(4), .my-orders table.views-table td:nth-child(4)').hide();
            $('.my-orders table.views-table th:nth-child(5), .my-orders table.views-table td:nth-child(5)').hide();
            $('.my-orders table.views-table th:nth-child(6), .my-orders table.views-table td:nth-child(6)').hide();
            $('.my-orders table.views-table th:nth-child(7), .my-orders table.views-table td:nth-child(7)').show();
        } else if (windowWidth <= 768) {
            $('.my-orders table.views-table th:nth-child(3), .my-orders table.views-table td:nth-child(3)').show();
            $('.my-orders table.views-table th:nth-child(4), .my-orders table.views-table td:nth-child(4)').hide();
            $('.my-orders table.views-table th:nth-child(5), .my-orders table.views-table td:nth-child(5)').hide();
            $('.my-orders table.views-table th:nth-child(6), .my-orders table.views-table td:nth-child(6)').hide();
            $('.my-orders table.views-table th:nth-child(7), .my-orders table.views-table td:nth-child(7)').show();
        } else if (windowWidth <= 1024) {
            $('.my-orders table.views-table th:nth-child(3), .my-orders table.views-table td:nth-child(3)').show();
            $('.my-orders table.views-table th:nth-child(4), .my-orders table.views-table td:nth-child(4)').show();
            $('.my-orders table.views-table th:nth-child(5), .my-orders table.views-table td:nth-child(5)').show();
            $('.my-orders table.views-table th:nth-child(6), .my-orders table.views-table td:nth-child(6)').hide();
            $('.my-orders table.views-table th:nth-child(7), .my-orders table.views-table td:nth-child(7)').show();
        } else {
            $('.my-orders table.views-table th:nth-child(3), .my-orders table.views-table td:nth-child(3)').show();
            $('.my-orders table.views-table th:nth-child(4), .my-orders table.views-table td:nth-child(4)').show();
            $('.my-orders table.views-table th:nth-child(5), .my-orders table.views-table td:nth-child(5)').show();
            $('.my-orders table.views-table th:nth-child(6), .my-orders table.views-table td:nth-child(6)').show();
            $('.my-orders table.views-table th:nth-child(7), .my-orders table.views-table td:nth-child(7)').hide();
        }
    }

    // Run after DOM is ready
    $(document).ready(function() {
        setTimeout(adjustTableColumns, 100);  // Adjust after slight delay
    });

    // Adjust columns on window resize
    $(window).resize(function() {
        adjustTableColumns();
    });
})(jQuery, Drupal, drupalSettings);

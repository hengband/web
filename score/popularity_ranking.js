function storageAvailable(type) {
	  try {
		    var storage = window[type],
			      x = '__storage_test__';
		    storage.setItem(x, x);
		    storage.removeItem(x);
		    return true;
	  }
    catch(e) {
        return e instanceof DOMException && (
            // everything except Firefox
            e.code === 22 ||
                // Firefox
                e.code === 1014 ||
                // test name field too, because code might not be present
                // everything except Firefox
                e.name === 'QuotaExceededError' ||
                // Firefox
                e.name === 'NS_ERROR_DOM_QUOTA_REACHED') &&
            // acknowledge QuotaExceededError only if there's something already stored
            storage.length !== 0;
    }
}

function select_table(table_id)
{
    if (table_id === null) {
        if (storageAvailable('sessionStorage')) {
            table_id = sessionStorage.getItem('selected_table');
        }
        table_id = table_id ? table_id : "race_id";
    }

    $("table.statistics_table").hide();
    $("div#" + table_id + " table.statistics_table").show();

    $("a.table_select").css('font-weight', 'normal').css('font-size', 'small');
    $("a.table_select#" + table_id).css('font-weight', 'bold').css('font-size', 'large');

    if (storageAvailable('sessionStorage')) {
        sessionStorage.setItem('selected_table', table_id);
    }
}

$(function(){
    $(".tablesorter").tablesorter({
        sortList: [[1, 1]],
        headers: {
            0: {sorter: false}
        }
    });
    $(".table_select").on('click', function() {
        select_table($(this).attr("id"));
    });

    select_table(null);
});

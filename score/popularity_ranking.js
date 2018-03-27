function select_table(table_id)
{
    $("table.statistics_table").hide();
    $("div#" + table_id + " table.statistics_table").show();

    $("a.table_select").css('font-weight', 'normal').css('font-size', 'small');
    $("a.table_select#" + table_id).css('font-weight', 'bold').css('font-size', 'large');
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

    select_table("race_id"); //初期は種族を表示
});

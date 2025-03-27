function maintenance() {
    let mode;
    let xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
	$("#wait_layout").hide();
	mode = this.responseText;
	if (!mode.length) return;
	let dialogOpt = {
	     title: localization.key['dialog.maintenance.title'],
	     message : `${localization.key['dialog.maintenance.status']} ${mode}`,
	     btnClassSuccessText: localization.key['dialog.maintenance.en'],
	     btnClassFailText: localization.key['dialog.maintenance.dis'],
	     btnClassFail: "btn btn-info btn-sm",
	     onResolve: function() {
	      xmlhttp.open("POST","maintenance.php?enable");
	      xmlhttp.send();
	     },
	     onReject: function() {
	      xmlhttp.open("POST","maintenance.php?disable");
	      xmlhttp.send();
	     }
	};
	 redDialog.make(dialogOpt);
      }
    };
     xmlhttp.open("POST","maintenance.php?mode");
     xmlhttp.send();
}

function initTableSorting(tableSelector) {
  var originalRows = $(tableSelector + " tbody tr").toArray();

  $(tableSelector + " thead th").not(":first, :last").each(function(index) {
    if (index === 0) {
      $(this).addClass("reset-sort").css("cursor", "pointer");
    } else {
      $(this).addClass("sortable").css("cursor", "pointer");
    }
  });

  $(tableSelector + " thead th.sortable").click(function() {
    var table = $(this).parents("table").eq(0);
    var index = $(this).index();
    var rows = table.find("tbody tr").toArray().sort(comparer(index));

    this.asc = !this.asc;
    if (!this.asc) {
      rows = rows.reverse();
    }

    table.find("th").removeClass("sorted-asc sorted-desc");
    $(this).addClass(this.asc ? "sorted-asc" : "sorted-desc");

    table.find("tbody").empty();
    for (var i = 0; i < rows.length; i++) {
      table.find("tbody").append(rows[i]);
    }

    updateRowNumbers(table);
  });

  $(tableSelector + " thead th.reset-sort").click(function() {
    var table = $(this).parents("table").eq(0);

    table.find("th").removeClass("sorted-asc sorted-desc");

    table.find("tbody").empty();
    for (var i = 0; i < originalRows.length; i++) {
      table.find("tbody").append(originalRows[i]);
    }

    updateRowNumbers(table);
  });

  function comparer(index) {
    return function(a, b) {
      var valA = getCellValue(a, index);
      var valB = getCellValue(b, index);

      if (index === 2 || index === 3) {
        return parseFloat(valA === "-" ? 0 : valA) - parseFloat(valB === "-" ? 0 : valB);
      } else if (index === 4 || index === 5) {
        return parseDateOrDefault(valA) - parseDateOrDefault(valB);
      } else {
        return valA.toString().localeCompare(valB.toString());
      }
    };
  }

  function getCellValue(row, index) {
    var cell = $(row).children("td").eq(index);
    return cell.text().trim();
  }

  function parseDateOrDefault(dateStr) {
    if (dateStr === "-") {
      return 0;
    }

    var parts = dateStr.split(" ");
    var dateParts = parts[0].split("-");
    var timeParts = parts[1].split(":");

    return new Date(
      parseInt(dateParts[0]),
      parseInt(dateParts[1]) - 1,
      parseInt(dateParts[2]),
      parseInt(timeParts[0]),
      parseInt(timeParts[1]),
      parseInt(timeParts[2])
    ).getTime();
  }

  function updateRowNumbers(table) {
    table.find("tbody tr").each(function(index) {
      $(this).children("td").first().text(index + 1);
    });
  }

  if ($("head style.table-sort-indicators").length === 0) {
    $("<style>")
      .prop("type", "text/css")
      .addClass("table-sort-indicators")
      .html(`
        th.sorted-asc::after { content: " ▲"; }
        th.sorted-desc::after { content: " ▼"; }
      `)
      .appendTo("head");
  }
}

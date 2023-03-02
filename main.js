Date.prototype.getWeek = function () {
  // Create a copy of this date object
  var target = new Date(this.valueOf());
  // ISO week date weeks start on monday, so correct the day number
  var dayNr = (this.getDay() + 6) % 7;
  // Set the target to the thursday of this week so the
  // target date is in the right year
  target.setDate(target.getDate() - dayNr + 3);
  // ISO 8601 states that week 1 is the week with january 4th in it
  var jan4 = new Date(target.getFullYear(), 0, 4);
  // Number of days between target date and january 4th
  var dayDiff = (target - jan4) / (24 * 60 * 60 * 1000);
  // Calculate week number: Week starts on monday, so subtract one from the result if the target date is not in the same year as january 4th
  var weekNumber = Math.ceil(dayDiff / 7);
  if (target.getFullYear() > jan4.getFullYear()) {
    weekNumber -= 1;
  }
  return weekNumber;
};

Date.prototype.weekNumberToDateArray = function (weekNumber) {
  // Create a date object for January 4th of the current year (the first week of the year according to German rules)
  var jan4 = new Date(new Date().getFullYear(), 0, 4);
  // Get the day of the week for January 4th (0-6)
  var jan4Day = jan4.getDay();
  // Calculate the offset in days from the first day of the year (Monday)
  var offset = jan4Day > 0 ? jan4Day - 1 : 6;
  // Calculate the number of days from January 4th to the start of the given week
  var startDays = (weekNumber - 1) * 7 - offset;

  // Create a date object for the start of the given week
  var startDate = new Date(jan4.getTime());
  startDate.setDate(startDate.getDate() + startDays);

  // Create an empty array to store the dates
  var dateArray = [];
  // Create an array of weekday names
  var weekdays = [
    "Monday",
    "Tuesday",
    "Wednesday",
    "Thursday",
    "Friday",
    "Saturday",
    "Sunday",
  ];
  // Loop through each day of the week and add it to the array as an object
  for (var i = 0; i < 7; i++) {
    var date = new Date(startDate.getTime());
    date.setDate(date.getDate() + i);
    // Format the date as dd.mm.yyyy using slice and join methods
    var dateString = [date.getDate(), date.getMonth() + 1, date.getFullYear()]
      .map((n) => n.toString().padStart(2, "0"))
      .join(".");
    // Create an object with day, dateString and date properties and push it to the array
    var dateObject = { day: weekdays[i], dateString: dateString, date: date };
    dateArray.push(dateObject);
  }
  return dateArray;
};

$(document).ready(function () {
  let weeksAvailable = [];
  let games = [];

  function generateDates() {
    var i = $("#week-select option:selected").index();
    $(".day-card .date-label").each(function (index) {
      $(this).text(weeksAvailable[i].week[index].dateString);
    });
    $(".table-dates th").each(function (index) {
      if (index > 0) {
        $(this).text(weeksAvailable[i].week[index - 1].dateString);
      }
    });
  }

  function initialLoad() {
    loadNameLocalInput();
    let today = new Date();
    let currentWeekNum = today.getWeek();
    weeksAvailable = [
      {
        num: currentWeekNum,
        week: today.weekNumberToDateArray(currentWeekNum),
      },
    ];
    for (let wn = currentWeekNum + 1; wn < currentWeekNum + 4; wn++) {
      weeksAvailable.push({ num: wn, week: today.weekNumberToDateArray(wn) });
    }

    buildWeekOptions();

    $(".day-card .date-label").each(function (index) {
      $(this).text(weeksAvailable[0].week[index].dateString);
    });
    $(".table-dates th").each(function (index) {
      if (index > 0) {
        $(this).text(weeksAvailable[0].week[index - 1].dateString);
      }
    });

    $.ajax({
      url: window.location.origin + "/api.php",
      type: "GET",
      dataType: "json",
      data: {
        action: "getGames",
      },
      success: function (data) {
        console.log(data);
        games = data;

        html = "";
        games.forEach((game) => {
          html += `<option value="${game.id}">${game.name} (${game.gm})<option>`;
        });
        $("#game-select").html(html);

        loadAttendanceTable();
        cleanSelects();
      },
    });
  }

  function loadPreviousChoices() {
    let localName = loadNameLocal();
    console.log(localName);
    if (!localName) {
      return;
    }
    $.ajax({
      url: window.location.origin + "/api.php",
      type: "GET",
      dataType: "json",
      data: {
        action: "getPersonByNameIP",
        name: localName,
      },
      success: function (data) {
        if (data.error) {
          return;
        }
        $.ajax({
          url: window.location.origin + "/api.php",
          type: "GET",
          dataType: "json",
          data: {
            action: "getAttendancePGW",
            person_id: data.id,
            game_id: $("#game-select").val(),
            weekNum: $("#week-select").val(),
            year: new Date().getFullYear(),
          },
          success: function (data) {
            if (data.error) {
              return;
            }
            $(".day-card").each(function (index) {
              if (data[index] == 1) {
                $(this).addClass("selected");
              } else {
                $(this).removeClass("selected");
              }
            });
          },
        });
      },
    });
  }

  function saveTimes() {
    if (
      $("#name-input").val() == "" ||
      $("#game-select").val() == "" ||
      $("#week-select").val() == ""
    ) {
      $("#failure-popup-text").text(
        "Please enter a name and select a game and week."
      );
      $("#fail-popup").fadeIn();
      return;
    }

    let days = [];
    $(".day-card").each(function (index) {
      days.push($(this).hasClass("selected") ? 1 : 0);
    });
    $.ajax({
      url: window.location.origin + "/api.php",
      type: "GET",
      dataType: "json",
      data: {
        action: "upsertAttendance",
        name: $("#name-input").val(),
        useragent: navigator.userAgent,
        game_id: $("#game-select").val(),
        year: new Date().getFullYear(),
        weekNum: $("#week-select").val(),
        monday: days[0],
        tuesday: days[1],
        wednesday: days[2],
        thursday: days[3],
        friday: days[4],
        saturday: days[5],
        sunday: days[6],
      },
      success: function (data) {
        if (data.success) {
          $("#success-popup-text").text("Your availability has been saved.");
          $("#success-popup").fadeIn();
          loadAttendanceTable();
          saveNameLocal();
        }
      },
    });
  }

  function saveNameLocal() {
    localStorage.setItem("name", $("#name-input").val());
  }

  function loadNameLocalInput() {
    let name = localStorage.getItem("name");
    if (name) {
      $("#name-input").val(name);
    }
  }

  function loadNameLocal() {
    let name = localStorage.getItem("name");
    return name;
  }

  function loadAttendanceTable() {
    $("#attendance-table tbody").html("");
    let game = $("#game-select").val();
    let week = $("#week-select").val();
    let year = new Date().getFullYear();
    $.ajax({
      url: window.location.origin + "/api.php",
      type: "GET",
      dataType: "json",
      data: {
        action: "getAttendanceForGameWeek",
        game_id: game,
        weekNum: week,
        year: year,
      },
      success: function (data) {
        if (data.error) {
          return;
        }
        data.forEach((person) => {
          let rowHTML = "<tr>";
          rowHTML += `<td>${person.name}</td>`;
          let yes = person.monday === "1";
          rowHTML += `<td class="${
            yes ? "tc table-success" : "tc table-danger"
          }">${
            yes
              ? '<i class="fa-solid fa-check">'
              : '<i class="fa-solid fa-xmark">'
          }</i></td>`;
          yes = person.tuesday === "1";
          rowHTML += `<td class="${
            yes ? "tc table-success" : "tc table-danger"
          }">${
            yes
              ? '<i class="fa-solid fa-check">'
              : '<i class="fa-solid fa-xmark">'
          }</i></td>`;
          yes = person.wednesday === "1";
          rowHTML += `<td class="${
            yes ? "tc table-success" : "tc table-danger"
          }">${
            yes
              ? '<i class="fa-solid fa-check">'
              : '<i class="fa-solid fa-xmark">'
          }</i></td>`;
          yes = person.thursday === "1";
          rowHTML += `<td class="${
            yes ? "tc table-success" : "tc table-danger"
          }">${
            yes
              ? '<i class="fa-solid fa-check">'
              : '<i class="fa-solid fa-xmark">'
          }</i></td>`;
          yes = person.friday === "1";
          rowHTML += `<td class="${
            yes ? "tc table-success" : "tc table-danger"
          }">${
            yes
              ? '<i class="fa-solid fa-check">'
              : '<i class="fa-solid fa-xmark">'
          }</i></td>`;
          yes = person.saturday === "1";
          rowHTML += `<td class="${
            yes ? "tc table-success" : "tc table-danger"
          }">${
            yes
              ? '<i class="fa-solid fa-check">'
              : '<i class="fa-solid fa-xmark">'
          }</i></td>`;
          yes = person.sunday === "1";
          rowHTML += `<td class="${
            yes ? "tc table-success" : "tc table-danger"
          }">${
            yes
              ? '<i class="fa-solid fa-check">'
              : '<i class="fa-solid fa-xmark">'
          }</i></td>`;
          rowHTML += "</tr>";
          $("#attendance-table tbody").append(rowHTML);
        });
      },
    });
  }

  function buildWeekOptions() {
    html = "";
    weeksAvailable.forEach((week) => {
      html += `<option value="${week.num}">Week ${week.num}<option>`;
    });
    $("#week-select").html(html);

    cleanSelects();
  }

  function cleanSelects() {
    $("select option")
      .filter(function () {
        return this.text == "";
      })
      .remove();
  }

  $(".day-card").each(function (index) {
    $(this).on("click", function () {
      if ($(this).hasClass("selected")) {
        $(this).removeClass("selected");
      } else {
        $(this).addClass("selected");
      }
    });
  });

  $("#save-button").on("click", function () {
    saveTimes();
  });

  $(".close-popup").on("click", function () {
    $(".popup").fadeOut();
  });

  $("#game-select").on("change", function () {
    loadAttendanceTable();
    loadPreviousChoices();
    generateDates();
  });

  $("#week-select").on("change", function () {
    loadAttendanceTable();
    loadPreviousChoices();
    generateDates();
  });

  initialLoad();
  loadPreviousChoices();
});

// window.navigator.userAgent

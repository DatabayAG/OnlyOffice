document.addEventListener("DOMContentLoaded", () => {
  const PLUGIN_ID = "xono";

  let config = {
    editing: {
      limited: false,
      withinTimeLimit: true,
      startTime: null,
      endTime: null,
    },
    file: {
      currentVersion: null,
      historyData: [],
      history: []
    },
    onlyOfficeConfig: {},
    backTarget: "",
  };

  let docEditor = null;

  const init = () => {
    config = window[`config_${PLUGIN_ID}`];
    delete window[`config_${PLUGIN_ID}`];

    const backButton = document.querySelector(".xono-back-button");
    backButton.addEventListener("click", () => {
      window.location.href = config.backTarget;
    })

    if (config.editing.limited) {
      if (config.editing.startTime) {
        config.editing.startTime = new Date(`${config.editing.startTime}Z`);
      }

      if (config.editing.endTime) {
        config.editing.endTime = new Date(`${config.editing.endTime}Z`);
      }
    }



    docEditor = new DocsAPI.DocEditor("xono_editor", config.onlyOfficeConfig);
  }

  const onAppReady = function () {
    if (config.editing.limited && !config.editing.withinTimeLimit) {
      docEditor.showMessage(il.Language.txt("editor_edit_timewasup"));
    }
    if (config.editing.limited && config.editing.withinTimeLimit) {
      docEditor.showMessage(il.Language.txt("editor_edit_period"));
    }
  };

  const onRequestHistory = function(event) {
    docEditor.refreshHistory({
      "currentVersion": config.file.currentVersion,
      "history": config.file.history
    });
  };

  const onRequestHistoryData = function(event) {
    const version = event.data;
    if (version > 1) {
      docEditor.setHistoryData(config.file.historyData[version]);
    }
  };

  const onDocumentStateChange = function (event) {
    fetch('https://worldtimeapi.org/api/timezone/Etc/UTC')
      .then(response => response.json())
      .then(data => {
        if (config.editing.limited) {
          if (!(data.unixtime > config.editing.startTime.getTime() / 1000 && data.unixtime < config.editing.endTime.getTime() / 1000)) {
            docEditor.denyEditingRights(il.Language.txt("editor_edit_timeup"));
          }
        }
      })
      .catch(error => {
        console.error('There has been a problem with your JavaScript time fetch operation, using local system time instead:', error);
        if (config.editing.limited) {
          const now = new Date();
          if (!(now.getTime() > config.end.getTime() && now.getTime() < config.editing.endTime.getTime())) {
            docEditor.denyEditingRights(il.Language.txt("editor_edit_timeup"));
          }
        }
      });
  };


  il.Util.addOnLoad(init);
});

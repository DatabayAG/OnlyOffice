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

    config.onlyOfficeConfig.events = {
      "onRequestHistory": onRequestHistory,
      "onRequestHistoryData": onRequestHistoryData,
      "onDocumentStateChange": onDocumentStateChange,
      "onAppReady": onAppReady
    }

    const backButton = document.querySelector(".xono-back-button");
    backButton.addEventListener("click", () => {
      window.location.href = config.backTarget;
    })

    if (config.editing.limited) {
      if (config.editing.startTime) {
        config.editing.startTime = new Date(config.editing.startTime * 1000);
      }

      if (config.editing.endTime) {
        config.editing.endTime = new Date(config.editing.endTime * 1000);
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
    checkEditingLimited();
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
    checkEditingLimited();
  };

  const checkEditingLimited = () => {
    fetch('https://worldtimeapi.org/api/timezone/Etc/UTC')
      .then(response => response.json())
      .then(data => {
        if (isEditingLimited(new Date(data.unixtime * 1000))) {
          docEditor.denyEditingRights(il.Language.txt("editor_edit_timeup"));
        }
      })
      .catch(error => {
        console.error('There has been a problem with your JavaScript time fetch operation, using local system time instead:', error);
        if (isEditingLimited(new Date())) {
          docEditor.denyEditingRights(il.Language.txt("editor_edit_timeup"));
        }
      });
  }

  const isEditingLimited = (date) => {
    if (!config.editing.limited) {
      return false;
    }

    return (
      date.getTime() < config.editing.startTime.getTime()
      || date.getTime() > config.editing.endTime.getTime()
    );
  }


  il.Util.addOnLoad(init);
});

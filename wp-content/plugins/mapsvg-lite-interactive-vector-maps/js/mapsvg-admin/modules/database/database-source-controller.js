;(function ($, window, MapSVG) {
  var MapSVGAdminDatabaseSourceController = function (container, admin, _mapsvg) {
    var _this = this
    this.name = "database-source"

    this.schemaRepo = new mapsvg.useRepository("schemas", _mapsvg)
    this.schemaRepo.init()
    this.schemaRepo.events.on("afterCreate", () => {
      this.schemaRepo.find()
    })
    this.schemaRepo.events.on("afterDelete", () => {
      this.schemaRepo.find()
    })
    this.schemaRepo.events.on("afterUpdate", () => {
      this.schemaRepo.find()
    })
    this.schemaRepo.events.on("afterLoad", () => {
      this.redraw()
    })

    this.schemas = []

    MapSVGAdminController.call(this, container, admin, _mapsvg)
  }
  window.MapSVGAdminDatabaseSourceController = MapSVGAdminDatabaseSourceController
  MapSVG.extend(MapSVGAdminDatabaseSourceController, window.MapSVGAdminController)

  MapSVGAdminDatabaseSourceController.prototype.setEventHandlers = function () {
    var _this = this
    this.view.on("click", "#mapsvg-go-to-table", function (e) {
      $(".tooltip").remove()
      e.preventDefault()
      _this.admin.slideToController(_this, "database", "forward")
      return false
    })

    this.contentView.on("click", function (e) {
      console.log(e.target)
    })

    _this.view.on("click", "#mapsvg-btn-add-datasource", function (e) {
      _this.showDataSourceModal()
    })

    this.contentView.on("click", '[data-action="edit-data-source"]', function () {
      let id = parseInt($(this).attr("data-schema-id"))
      _this.schemaRepo.findById(id).done((schema) => {
        _this.showDataSourceModal(schema)
      })
    })

    this.contentView.on("click", '[data-action="set-data-source"]', function () {
      var id = $(this).attr("data-schema-id")
      if (!id) {
        return
      }
      _this.setDataSource(parseInt(id.trim(), 10))
    })

    this.contentView.on("click", '[data-action="delete-data-source"]', function () {
      var id = $(this).attr("data-schema-id")
      if (!id) {
        return
      }
      _this.deleteDataSource(parseInt(id.trim(), 10))
    })
    $(window).on("keydown.form.database-source", (e) => {
      // @ts-ignore
      if ((e.metaKey || e.ctrlKey) && e.keyCode == 13)
        // @ts-ignore
        this.formBuilder && this.formBuilder.save()
      else if (e.keyCode == 27)
        // @ts-ignore
        this.formBuilder && this.formBuilder.close()
    })
  }

  MapSVGAdminDatabaseSourceController.prototype.viewLoaded = function () {
    var _this = this
    const query = new mapsvg.query({ perpage: 999999 })
    this.schemaRepo.find(query).done((response) => {
      this.schemas = response && response.length ? response : []
      this.redraw()
    })
    this.btnAdd = $("#mapsvg-btn-add-datasource")
  }

  /**
   * Connects the map to a schema (objects data source) by id and reloads data.
   * @param {number} id Schema id
   */
  MapSVGAdminDatabaseSourceController.prototype.setDataSource = function (id) {
    var _this = this

    if (!id) {
      return
    }

    _this.schemaRepo.findById(id).done(function (schema) {
      _this.mapsvg.update({
        database: { objectsTableName: schema.name, schemas: { objects: schema.getData() } },
      })
      _this.mapsvg.objectsRepository.setSchema(schema)

      _this.mapsvg.objectsRepository.find().done(function () {
        _this.redraw()
        var jsp = _this.contentWrap.data("jsp")
        if (jsp) {
          jsp.scrollToY(0)
        }
        _this.admin.slideToController(_this, "database", "forward")
      })
    })
  }

  /**
   * Deletes a data source by schema id.
   * Not allowed for the connected source or for region schemas.
   * @param {number} id Schema id
   */
  MapSVGAdminDatabaseSourceController.prototype.deleteDataSource = function (id) {
    var _this = this

    if (!id) {
      return
    }

    var currentSchema = _this.mapsvg.objectsRepository.getSchema()
    if (currentSchema && String(currentSchema.id) === String(id)) {
      $.growl.error({
        title: "",
        message: "Disconnect the data source before deleting",
        duration: 2000,
      })
      return
    }

    var loaded = _this.schemaRepo.getLoadedObject(id)
    if (loaded && loaded.type === "region") {
      $.growl.error({
        title: "",
        message: "Region data sources cannot be deleted",
        duration: 2000,
      })
      return
    }

    if (!confirm("Delete this data source and its database table? This cannot be undone.")) {
      return
    }

    _this.schemaRepo
      .delete(id)
      .done(function () {
        $.growl.notice({ title: "", message: "Data source deleted", duration: 700 })
        _this.redraw()
      })
      .fail(function (response) {
        mapsvg.utils.http.handleFailedRequest(response)
      })
  }

  MapSVGAdminDatabaseSourceController.prototype.getTemplateData = function () {
    var _this = this
    const tableName = this.mapsvg.objectsRepository.getSchema().name

    _this.schemas.sort(function (a, b) {
      return a.table_name == tableName ? -1 : b.table_name == tableName ? 1 : 0
    })
    return {
      schemas: _this.schemas.sort(),
      defaultTable: tableName,
    }
  }

  MapSVGAdminDatabaseSourceController.prototype.addDataRow = function (obj) {
    var _this = this
    var d = {
      fields: _this.schemaRepo.getColumns({ visible: true }),
      params: obj,
    }
    for (var i in d.fields) {
      if (d.fields[i].type == "region") {
        d.fields[i].options = []
        d.fields[i].optionsDict = {}
        _this.mapsvg.getData().regions.forEach(function (region) {
          d.fields[i].options.push({ id: region.id, title: region.title })
          d.fields[i].optionsDict[region.id] = region.title ? region.title : region.id
        })
      }
    }
    var row = $(_this.templates.item(d))
    this.view.find("#mapsvg-data-list-table tbody").prepend(row)
    return row
  }

  MapSVGAdminDatabaseSourceController.prototype.updateDataRow = function (obj, row) {
    var _this = this
    var d = {
      fields: _this.schemaRepo.getColumns({ visible: true }),
      params: obj,
    }
    for (var i in d.fields) {
      if (d.fields[i].type == "region") {
        d.fields[i].options = []
        d.fields[i].optionsDict = {}
        _this.mapsvg.getData().regions.forEach(function (region) {
          d.fields[i].options.push({ id: region.id, title: region.title })
          d.fields[i].optionsDict[region.id] = region.title ? region.title : region.id
        })
      }
    }

    var newRow = $(_this.templates.item(d))
    row = row || $("#mapsvg-datasource-" + obj.id)
    row.replaceWith(newRow)
    newRow.addClass("mapsvg-row-updated")

    setTimeout(function () {
      newRow.removeClass("mapsvg-row-updated")
    }, 2600)
  }

  MapSVGAdminDatabaseSourceController.prototype.deleteDataRow = function (row) {
    var id = row.data("id")
    if (!id) {
      return
    }
    this.deleteDataSource(parseInt(id, 10))
  }

  MapSVGAdminDatabaseSourceController.prototype.showDataSourceModal = function (schema) {
    var _this = this

    const newRecord = typeof schema === "undefined"

    if (this.tableDataActiveRow) this.tableDataActiveRow.removeClass("mapsvg-row-selected")
    if (schema && schema.id) {
      this.tableDataActiveRow = $("#mapsvg-data-" + schema.id)
      this.tableDataActiveRow.addClass("mapsvg-row-selected")
    } else {
      this.tableDataActiveRow = null
    }
    this.btnAdd.addClass("disabled")
    if (_this.formBuilder) {
      _this.formBuilder.destroy()
      _this.formBuilder = null
      _this.formBuilderRow && _this.formBuilderRow.remove()
    }
    if (_this.formContainer) _this.formContainer.empty().remove()

    _this.formContainer = $('<div class="mapsvg-modal-edit"></div>')
    this.contentWrap.append(_this.formContainer)

    // var marker_id = object.marker && object.marker.id ? object.marker.id : '';
    // _this.mapsvg.hideMarkersExceptOne(marker_id);

    var post_type_options = window.mapsvgAdmin.getData().options.postTypes.map(function (option) {
      return { label: option, value: option }
    })

    const options = [
      { label: "Mapsvg Database", value: "object" },
      { label: "WP Posts", value: "post" },
      { label: "API source", value: "api" },
    ]
    options[1].premium = true
    

    options[2].premium = true
    

    const fields = [
      {
        name: "type",
        label: "Data source",
        type: "radio",
        options,
        readonly: !newRecord,
      },
      {
        name: "title",
        label: "Title",
        type: "text",
        help: `Example: "Sales representatives"`,
      },
      // {
      //   name: "name",
      //   label: "Name",
      //   type: "text",
      //   help: `Unique, without spaces. Example: "sales_reps". Can't be changed after creation!`,
      //   readonly: !newRecord,
      // },
      {
        name: "objectNameSingular",
        label: "Object name singular",
        type: "text",
        help: `Example: "user"`,
      },
      {
        name: "objectNamePlural",
        label: "Object name plural",
        type: "text",
        help: `Example: "users"`,
      },
      { name: "postType", label: "Post type", type: "select", options: post_type_options },
      {
        name: "apiBaseUrl",
        label: "API base URL",
        type: "text",
        help: `If left empty, default MapSVG WordPress REST API will be used: https://yoursite.com/wp-json/mapsvg/v1/`,
      },
      {
        name: "apiEndpoints",
        label: "API endpoints",
        format: "json",
        type: "textarea",
        help: `API endpoints in JSON format. Without the base URL.`,
      },
      {
        name: "apiAuthorization",
        label: "API authorization",
        type: "text",
        help: `Type and token. Example: "Bearer 1/mZ1edKKACtPAb7zGlwSzvs72PvhAbGmB8K1ZrGxpcNM". Leave empty if API doesn't require authorization.`,
      },
    ]

    var schemaForModal = new mapsvg.schema({ type: "schema", fields })

    let schemaData

    if (!schema) {
      schema = new mapsvg.schema({
        type: "object",
        apiBaseUrl: "",
        apiEndpoints: [
          {
            method: "GET",
            name: "index",
            url: "/",
          },
          {
            method: "GET",
            name: "show",
            url: "/[:id]",
          },
          {
            method: "POST",
            name: "create",
            url: "/",
          },
          {
            method: "PUT",
            name: "update",
            url: "/[:id]",
          },
          {
            method: "DELETE",
            name: "delete",
            url: "/[:id]",
          },
          {
            method: "DELETE",
            name: "clear",
            url: "/",
          },
        ],
      })
    }

    schemaData = $.extend(true, {}, schema)
    schemaData.apiEndpoints = JSON.stringify(schema.apiEndpoints, null, 2)

    const setFormElementsByType = (type) => {
      const apiElems = ["apiEndpoints", "apiBaseUrl", "apiAuthorization"]

      const toggleElems = (names, val) => {
        for (const name of names) {
          $(_this.formBuilder.getFormElementByName(name).domElements.main).toggle(val)
        }
      }
      // var postTypeField = _this.formContainer.find('[name="postType"]').closest(".form-group")
      // // postTypeField.hide()
      // _this.formContainer.on("change", 'input[name="type"]', function () {
      //   postTypeField.toggle($(this).val() === "posts")
      // })

      switch (type) {
        case "api": {
          toggleElems(apiElems, true)
          toggleElems(["postType"], false)
          $(_this.formBuilder.getFormElementByName("postType").domElements.main).hide()
          break
        }
        case "post": {
          toggleElems(apiElems, false)
          toggleElems(["postType"], true)
          $(_this.formBuilder.getFormElementByName("postType").domElements.main).show()
          break
        }
        case "object": {
          toggleElems(apiElems, false)
          toggleElems(["postType"], false)
          $(_this.formBuilder.getFormElementByName("postType").domElements.main).hide()
          break
        }
        default:
          null
      }
    }

    // if (schemaData.name && schemaData.type === "post") {
    //   schemaData.postType = schemaData.name.split("_").slice(1).join("_")
    // }

    _this.formBuilder = new mapsvg.formBuilder({
      container: _this.formContainer,
      schema: schemaForModal,
      editMode: false,
      showNames: false,
      mapsvg: _this.mapsvg,
      mediaUploader: _this.admin.mediaUploader,
      data: schemaData,
      admin: _this.admin,
      closeOnSave: true,
      events: {
        "change.formElement": (event) => {
          const { formBuilder, formElement } = event.data
          if (formElement.name === "type") {
            setFormElementsByType(formElement.value)
          }
        },
        save: function (event) {
          let {
            data: { formBuilder, data },
          } = event

          if (data.type === "object" || data.type === "post") {
            data.apiBaseUrl = ""
            data.apiEndpoints = null
            data.apiAuthorization = ""
          }
          // if (data.type === "post") {
          //   data.name = "posts_" + data.postType
          // }

          if (data.type === "object") {
            data.postType = null
          }

          if (data.type === "api") {
            data.postType = null
            try {
              if (!data.apiEndpoints) {
                $.growl.error({
                  title: "",
                  message: 'Enter at least one API endpoint with the name "index"',
                  duration: 700,
                })
                return
              }
              data.apiEndpoints = JSON.parse(data.apiEndpoints)
            } catch (e) {
              $.growl.error({ title: "", message: "Invalid JSON for API endpoints", duration: 700 })
              return
            }
          }

          if (data.type === "post") {
            if (!data.postType) {
              $.growl.error({ title: "", message: "Choose post type", duration: 700 })
              return
            }
          }

          // if (data.type !== "post" && !data.name) {
          //   $.growl.error({ title: "", message: "Enter the name", duration: 700 })
          //   return
          // }
          if (!data.title) {
            $.growl.error({ title: "", message: "Enter the title", duration: 700 })
            return
          }
          if (!data.objectNameSingular) {
            $.growl.error({ title: "", message: "Enter the object name singular", duration: 700 })
            return
          }
          if (!data.objectNamePlural) {
            $.growl.error({ title: "", message: "Enter the object name plural", duration: 700 })
            return
          }

          const prevObjectNamePlural = schema.objectNamePlural
          const pluralChanged = prevObjectNamePlural !== data.objectNamePlural

          schema.update(data)

          if (newRecord) {
            _this.schemaRepo
              .create(schema)
              .done(function (createdSchema) {
                formBuilder.close()
                $.growl.notice({ title: "", message: "Data source created", duration: 700 })
                // _this.setDataSource(createdSchema.id)
              })
              .fail((response) => {
                mapsvg.utils.http.handleFailedRequest(response)
              })
          } else {
            _this.schemaRepo
              .update(schema)
              .done(function () {
                formBuilder.close()
                $.growl.notice({ title: "", message: "Date source updated", duration: 700 })
              })
              .fail((response) => {
                mapsvg.utils.http.handleFailedRequest(response)
              })
          }
        },
        close: function () {
          _this.closeFormHandler()
        },
        init: function (event) {
          setFormElementsByType(schema.type)
          setTimeout(() => {
            $(".tooltip").remove()
          }, 200)
        },
      },
    })
    _this.formBuilder.init()
  }

  // MapSVGAdminDatabaseSourceController.prototype.copyDataObject = function (id) {
  //   var _this = this

  //   var object = {}
  //   $.extend(object, _this.schemaRepo.getLoadedObject(id))
  //   object.id = null
  //   // delete object.id;

  //   if (object.location && object.location instanceof MapSVG.Location) {
  //     // TODO fix this shit
  //     var location = object.location.toJSON()
  //     object.location = new MapSVG.Location(location)
  //     new MapSVG.Marker({
  //       location: object.location,
  //       mapsvg: _this.mapsvg,
  //       object: object,
  //     })
  //   }

  //   _this.editDataObject(object, false, true)
  // }

  MapSVGAdminDatabaseSourceController.prototype.closeFormHandler = function () {
    var _this = this
    _this.btnAdd.removeClass("disabled")
    if (_this.formBuilder) {
      _this.formBuilder.destroy()
      _this.formBuilder = null
      _this.formContainer.empty().remove()
      // _this.formBuilderRow && _this.formBuilderRow.remove();
      _this.tableDataActiveRow && _this.tableDataActiveRow.removeClass("mapsvg-row-selected")
      _this.tableDataActiveRow &&
        !_this.tableDataActiveRow.hasClass("mapsvg-row-updated") &&
        _this.tableDataActiveRow.addClass("mapsvg-row-closed")
      setTimeout(function () {
        _this.tableDataActiveRow &&
          !_this.tableDataActiveRow.hasClass("mapsvg-row-updated") &&
          _this.tableDataActiveRow.removeClass("mapsvg-row-closed")
      }, 1600)
      // WP Media Uploader inserts a.browser links, remove them:
      $("a.browser").remove()

      if (_this.admin.getData().mode === "editMarkers") {
        _this.admin && _this.admin.enableMarkersMode(false)
        _this.admin.setPreviousMode()
      }
    }
    this.updateScroll()
  }
})(jQuery, window, window.MapSVG)

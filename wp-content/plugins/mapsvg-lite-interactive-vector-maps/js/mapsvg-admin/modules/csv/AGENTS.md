# CSV Module — ID Prefixing

## The Problem

The CSV template (`csv.html`) is shared by **two tabs**:

| Tab                         | Controller                              | `idPrefix`   |
|-----------------------------|-----------------------------------------|--------------|
| Database → Import from CSV  | `database-csv-controller.js`            | `objects`    |
| Regions → Import from CSV   | `regions-csv-controller.js`             | `regions`    |

Both tabs can be open simultaneously. If the template uses bare IDs like `id="mapsvg-csv-file"`, the DOM would contain duplicate IDs — which is invalid HTML and breaks `label[for]` associations (browsers resolve `getElementById` globally).

## The Solution

**IDs are auto-prefixed at runtime** — the template uses bare, readable IDs, and `_prefixIds()` runs during `redraw()` to prepend the scoped prefix.

### Controller (`csv-controller.js`)

1. **`redraw()`** overrides the parent method and calls `_prefixIds()` after template rendering:

   ```js
   MapSVGAdminCsvController.prototype.redraw = function () {
     MapSVGAdminController.prototype.redraw.call(this)
     this._prefixIds()
   }
   ```

2. **`_prefixIds()`** prepends `this.templateData.idPrefix + "-"` to every `[id]` and `label[for]` inside `this.view`:

   ```js
   MapSVGAdminCsvController.prototype._prefixIds = function () {
     var prefix = this.templateData.idPrefix + "-"
     this.view.find("[id]").each(function () {
       var $el = $(this)
       $el.attr("id", prefix + $el.attr("id"))
     })
     this.view.find("label[for]").each(function () {
       var $label = $(this)
       $label.attr("for", prefix + $label.attr("for"))
     })
   }
   ```

3. **`$id(name)`** turns a bare ID name into a scoped jQuery selector:

   ```js
   // Returns e.g. "#objects-mapsvg-csv-file"
   MapSVGAdminCsvController.prototype.$id = function (name) {
     return "#" + this.templateData.idPrefix + "-" + name
   }
   ```

4. **ALL jQuery selectors** in the controller MUST use `$id()`. Examples:

   ```js
   // ❌ WRONG — will pick up the wrong tab's element
   _this.view.find("#mapsvg-csv-file")

   // ✅ CORRECT
   _this.view.find(_this.$id("mapsvg-csv-file"))
   ```

   For multi-selector patterns, join them:
   ```js
   _this.view.find(
     _this.$id("gs-common-checked-panel") + ", " +
     _this.$id("gs-validated-blocks") + ", " +
     _this.$id("gs-validated-actions")
   )
   ```

### Template (`csv.html`)

- **Use bare, readable IDs** — do NOT add `{{idPrefix}}-` to IDs or `for` attributes.
- `_prefixIds()` handles all prefixing automatically.
- The template is simple and the prefixing logic lives in one place.

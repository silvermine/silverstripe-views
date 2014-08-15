;(function() {
   "use strict";

   var $ = jQuery,
       ViewsModel, chosenOptions;


   ViewsModel = {
      type: 'ViewsModel',
      name: '',
      help: '',
      fields: {},

      children: {},

      /**
       * Build a select element to control a boolean field
       *
       * @param string property Object Property to link to
       * @param string name Element Label
       * @param string yesOptionLabel Optional
       * @param string noOptionLabel Optional
       * @return jQuery Object
       */
      buildBoolSelect: function(property, name, yesOptionLabel, noOptionLabel) {
         var self = this,
             input = $('<select></select>'),
             yesOption = $('<option></option>'),
             noOption = $('<option></option>'),
             label = this.buildInputLabel(property, name),
             container = this.buildInputContainer(property);

         input.addClass(property);
         input.addClass('dropdown');

         yesOptionLabel = yesOptionLabel || "Yes";
         yesOption.html(yesOptionLabel);
         yesOption.attr('value', 1);

         noOptionLabel = noOptionLabel || "No";
         noOption.html(noOptionLabel);
         noOption.attr('value', 0);

         (!!this[property] ? yesOption : noOption).attr('selected', 'selected');

         yesOption.appendTo(input);
         noOption.appendTo(input);

         input.change(function() {
            var val = parseInt($(this).val(), 10);
            self[property] = !!val;
         });

         container.append(label);
         container.append(input);
         return container;
      },


      /**
       * Return the container for a form element
       *
       * @param string property Object Property name
       * @return jQuery Object
       */
      buildInputContainer: function(property) {
         var container = $('<span></span>');
         container.addClass(property);
         container.addClass('inputGrouping');
         return container;
      },


      /**
       * Build an input label
       *
       * @param string property Object Property to link to
       * @param string name Element Label
       * @return jQuery Object
       */
      buildInputLabel: function(property, name) {
         var label = $('<label></label>');
         label.addClass(property);
         label.addClass('left');
         label.html(name);
         return label;
      },


      /**
       * Build a Text Input
       *
       * @param string property Object Property to link to
       * @param string name Element Label
       * @return jQuery Object
       */
      buildTextInput: function(property, name) {
         var self = this,
             input = $('<input type="text" />'),
             label = this.buildInputLabel(property, name),
             container = this.buildInputContainer(property);

         input.addClass(property);
         input.addClass('text');

         input.val(this[property]);
         input.keyup(function() {
            self[property] = $(this).val();
         });

         container.append(label);
         container.append(input);
         return container;
      },


      /**
       * Build a Select Input
       *
       * @param string property Object Property to link to
       * @param string name Element Label
       * @param array choices Select options
       * @return jQuery Object
       */
      buildSelectInput: function(property, name) {
         var self = this,
             input = $('<select></select>'),
             label = this.buildInputLabel(property, name),
             container = this.buildInputContainer(property),
             choices = this.fields[property].options,
             choice, key, value;

         input.addClass(property);
         input.addClass('dropdown');

         for (key in choices) {
            if (choices.hasOwnProperty(key)) {
               value = choices[key];
               choice = $('<option></option>');
               choice.attr('value', key);
               choice.html(value);

               if (this[property] == key || this[property] == value) {
                  choice.attr('selected', 'selected');
               }

               input.append(choice);
            }
         }

         input.change(function() {
            self[property] = $(this).val();
         });

         container.append(label);
         container.append(input);

         return container;
      },

      /**
       * Dynamically construct an object subclass from the given
       * class descriptions.
       *
       * @param string type Name of subclass
       * @param array prototypes Class metadata descriptions
       */
      constructPrototype: function(type, prototypes) {
         var proto = prototypes[type],
             base = proto.base,
             cls;

         if (this.children.hasOwnProperty(type)) {
            return;
         }

         if (base && !this.children.hasOwnProperty(base)) {
            this.constructPrototype(base, prototypes);
         }

         base = base ? this.getPrototype(base) : this;
         cls = Object.create(base);
         cls.constructor = base;
         cls.type = type;
         cls.fields = proto.fields;
         this.children[type] = cls;
      },


      /**
       * Dynamically construct object subclasses from the
       * given class descriptions.
       *
       * @param array prototypes Class metadata descriptions
       */
      constructPrototypes: function(prototypes) {
         var type;

         for (type in prototypes) {
            if (prototypes.hasOwnProperty(type)) {
               this.constructPrototype(type, prototypes);
            }
         }
      },

      /**
       * Get the HTML class name used to represent this object and it's parents
       *
       * @return string
       */
      getClassName: function() {
         var name = [],
             proto = this.getPrototype(this.type);

         if (proto && proto.constructor && this.type != 'ViewsModel') {
            name = this.constructor.getClassName();
         }

         name.push(this.type);
         return name;
      },


      /**
       * Get the default value for a field
       *
       * @param string name
       * @return mixed default
       */
      getFieldDefault: function(name) {
         return this.fields[name].default;
      },


      /**
       * Get the input type for the given field
       *
       * @param string name
       * @return string Input Type
       */
      getFieldType: function(name) {
         return this.fields[name].type;
      },


      /**
       * Return the object of the given type string
       *
       * @param string type
       */
      getPrototype: function(type) {
         return this.children[type];
      },


      /**
       * Return an HTML interface representing the model
       *
       * @return html
       */
      html: function () {
         var container = $('<div></div>'),
             classes = this.getClassName(),
             key, i;

         container.append('<h4>' + this.type + '</h4>');
         for (i = 0; i < classes.length; i++) {
            container.addClass(classes[i]);
         }

         for (key in this.fields) {
            if (this.fields.hasOwnProperty(key)) {
               container.append(this.renderField(key));
            }
         }

         return container;
      },


      /**
       * Recursively import an object data representation
       *
       * @param array repr
       */
      importRepr: function(repr) {
         var property,
             child,
             key,
             i;

         repr.fields = repr.fields || {};
         for (key in this.fields) {
            if (this.fields.hasOwnProperty(key)) {
               property = repr.fields[key];

               switch (this.getFieldType(key)) {
                  case 'has_one':
                     property = property || {};
                     child = this.instantiateType(property);
                     this[key] = [];
                     if (child) {
                        this[key].push(child);
                     }
                     break;

                  case 'has_many':
                     this[key] = [];
                     property = property || [];
                     for (i = 0; i < property.length; i++) {
                        child = this.instantiateType(property[i]);
                        this[key].push(child);
                     }
                     break;

                  case 'bool':
                     this[key] = property ? parseInt(property, 2) : 0;
                     break;

                  default:
                     this[key] = property;
                     break;
               }
            }
         }
      },


      /**
       * Given an object repr, return a new instance of it.
       *
       * @param object repr
       */
      instantiateType: function(repr) {
         var proto = this.getPrototype(repr.type),
             obj;

         if (!proto) {
            return null;
         }

         obj = Object.create(proto);
         obj.importRepr(repr);
         return obj;
      },


      /**
       * Return the HTML used to represent a field
       *
       * @param string field
       * @return html
       */
      renderField: function(field) {
         switch (this.getFieldType(field)) {
            case 'has_one':
               return this.renderSet(field, field, 1);

            case 'has_many':
               return this.renderSet(field, field);

            case 'bool':
               return this.buildBoolSelect(field, field);

            case 'select':
               return this.buildSelectInput(field, field);

            default:
               return this.buildTextInput(field, field);
         }
      },


      /**
       * Render a ForeignKey relationship as HTML, including controls
       * for adding / removing objects.
       *
       * @param string property Object Property
       * @param object repr data Representation
       * @param integer limit Defaults to 0 (no limit). Max number of objects
       */
      renderSet: function(property, title, limit) {
         var container = $('<div></div>'),
             heading = $('<h4></h4>'),
             self = this,
             allowedTypes = Object.keys(this.fields[property].options),
             i, item, rmButton, addButton, type, rmCallback, addCallback, childObjects;

         limit = limit || 0;

         container.addClass(property);
         container.addClass('objectSet');

         heading.html(title);
         heading.addClass('setHeader');
         container.append(heading);

         rmCallback = function() {
            var i = $(this).data('index'),
                item = self[property][i],
                msg = "Are you sure you want to delete this object? " + item.type + "#" + (i + 1);

            if (confirm(msg)) {
               self[property].splice(i, 1);
               $(document).trigger('redrawQueryBuilder');
            }
         };

         addCallback = function() {
            self[property].push(self.instantiateType({
               type: $(this).data('type')
            }));

            $(document).trigger('redrawQueryBuilder');
         };

         if (this[property].length <= 0) {
            container.append('<p>None</p>');
         }

         for (i = 0; i < this[property].length; i++) {
            item = this[property][i];

            rmButton = $('<span class="rmButton">Remove</span>');
            rmButton.data('index', i);
            rmButton.click(rmCallback);

            item = item.html();
            item.prepend(rmButton);
            container.append(item);
         }

         if (this[property].length < limit || limit === 0) {
            for (i = 0; i < allowedTypes.length; i++) {
               type = allowedTypes[i];
               addButton = $('<span>Add ' + this.getPrototype(type).type + '</span>');
               addButton.addClass('addButton');
               addButton.addClass(type);
               addButton.attr('title', this.getPrototype(type).help);
               addButton.data('type', type);
               addButton.click(addCallback);

               container.append(addButton);
            }
         }

         return container;
      },


      /**
       * Create a JSON serializable representation of this object.
       *
       * @return object
       */
      repr: function() {
         var obj, key, i;
         obj = {
            type: this.type,
            fields: {}
         };

         for (key in this.fields) {
            if (this.fields.hasOwnProperty(key)) {
               switch (this.getFieldType(key)) {
                  case 'has_one':
                     obj.fields[key] = this[key].length ? this[key][0].repr() : null;
                     break;

                  case 'has_many':
                     obj.fields[key] = [];
                     for (i = 0; i < this[key].length; i++) {
                        obj.fields[key].push(this[key][i].repr());
                     }
                     break;

                  default:
                     obj.fields[key] = this[key] || this.getFieldDefault(key);
                     break;
               }
            }
         }

         return obj;
      }
   };


   chosenOptions = {
      disable_search_threshold: 10,
      allow_single_deselect: true,
      width: "auto"
   };



   // Instantiate Query Editors and tie there edits to
   // the hidden JSON form they power.
   var init = function() {
      if (!JSON || !JSON.stringify) {
         ui.html('<p>Browser must support JSON tools. Modern Chrome or Firefox is recommended.</p>');
      }

      $('input.viewsQueryBuilderRepr').each(function() {
         var field = $(this),
             loadRepr, repr, importExport, save, query, ui;

         loadRepr = function() {
            var form, json;

            json = field.val();
            repr = $.parseJSON(json);

            // Dynamically construct classes from their definitions
            ViewsModel.constructPrototypes(repr.types);

            // Instantiates objects using the new class tree
            query = ViewsModel.instantiateType(repr.data);

            // Draw Form
            form = query.html();
            ui = field.parent().find('div.viewsQueryBuilder');
            ui.html(form);
            ui.find('select.dropdown').chosen(chosenOptions);
         };

         loadRepr();

         // Import / Export Views
         if (window.Blob && window.FileReader) {
            importExport = $(this).parent().find('div.viewsImportExport');
            importExport.append("<div class='export'><h2>Export View</h2><a>Download</a></div>");
            importExport.append("<div class='import'><h2>Import View</h2><input type='file' /><a>Import View</a></div>");

            importExport.find('div.import a').click(function() {
               if(!confirm("Are you sure you want to completely overwrite the above view?")) {
                  return;
               }

               var file = $(this).prev('input').get(0).files[0],
                   reader = new FileReader();

               reader.onload = function(event) {
                  field.val(event.target.result);
                  loadRepr();
               }

               reader.readAsText(file);
            });
         }

         // Save function
         save = function() {
            var json, blob, exportLink;
            repr.data = query.repr();
            json = JSON.stringify(repr);
            field.val(json);

            if (!window.Blob) {
               return;
            }

            exportLink = importExport.find('.export a');
            URL.revokeObjectURL(exportLink.attr('href'));

            blob = new Blob([json], {"type": "application\/json"});
            exportLink.attr('href', URL.createObjectURL(blob));
            exportLink.attr('download', "silverstripe-" + (new Date()).getTime() + ".view");
         };

         $('input, select').live('change', save);
         $('form').submit(save);
         $(document).bind('redrawQueryBuilder', function() {
            var form = query.html();
            ui.html(form);
            ui.find('select.dropdown').chosen(chosenOptions);

            save();
         });

         save();
      });
   };

   $(document).ready(init);
   $('body').on('DOMNodeInserted', function(e) {
      if ($(e.target).find('input.viewsQueryBuilderRepr').length == 0) {
         return;
      }

      return init();
   });

}());

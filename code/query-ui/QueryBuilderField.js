;(function() {
   "use strict";
   
   var $ = jQuery,
       ViewsModel,
       QueryResultsRetriever,
       QuerySort,
       QueryPredicate,
       CompoundPredicate,
       TaxonomyTermPredicate,
       FieldPredicate,
       FieldPredicateValue,
       PredicateCondition,
       CompoundPredicateCondition,
       QueryParamPredicateCondition;
   
   
   /**
    * Base object for all Views Data Model objects
    * 
    * @param object repr 
    */
   ViewsModel = function(repr, nlevel) {
      this.nlevel = nlevel;
   };
   
   
   /**
    * Return the object of the given type string
    * 
    * @param string type
    */
   ViewsModel.getType = function(type) {
      var types, i;
      
      types = [
         QueryResultsRetriever,
         QuerySort,
         QueryPredicate,
         CompoundPredicate,
         TaxonomyTermPredicate,
         FieldPredicate,
         FieldPredicateValue,
         PredicateCondition,
         CompoundPredicateCondition,
         QueryParamPredicateCondition
      ];
      
      for (i = 0; i < types.length; i++) {
         if (types[i].prototype.Type === type) {
            return types[i];
         }
      }
   };
   
   
   /**
    * Given an object repr, return a new instance of it.
    * 
    * @param object repr
    */
   ViewsModel.instantiateType = function(repr, nlevel) {
      var Type = ViewsModel.getType(repr.Type);
      return new Type(repr, nlevel);
   };
   
   ViewsModel.prototype = {
      nlevel: 0,
      Type: 'ViewsModel',
      Name: '',
      Help: '',
      
      
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
      buildSelectInput: function(property, name, choices) {
         var self = this,
             input = $('<select></select>'),
             label = this.buildInputLabel(property, name),
             container = this.buildInputContainer(property),
             choice, i;
         
         input.addClass(property);
         
         for (i = 0; i < choices.length; i++) {
            choice = $('<option></option>');
            choice.attr('value', choices[i]);
            choice.html(choices[i]);
            input.append(choice);
         }
         
         input.children('option[value="' + this[property] + '"]').attr('selected', 'selected');
         input.change(function() {
            self[property] = $(this).val();
         });
         
         container.append(label);
         container.append(input);
         return container;
      },
      
      
      /**
       * Return an HTML interface representing the model
       * 
       * @return html
       */
      html: function () {
         var container = $('<div></div>');
         container.addClass('ViewsModel');
         container.addClass('nlevel-' + this.nlevel);
         container.addClass(this.Type);
         container.append('<h4>' + this.Name + '</h4>');
         container.attr('title', this.Help);
         return container;
      },
      
      
      /**
       * Instantiates a ForeignKey relationship given it's name
       * and serialized representation.
       * 
       * @param string property Object Property
       * @param object repr Data Representation
       */
      instantiateSet: function(property, repr) {
         var level = this.nlevel + 1,
             i;
         
         this[property] = [];
         repr[property] = repr[property] || [];
         for (i = 0; i < repr[property].length; i++) {
            this[property].push(ViewsModel.instantiateType(repr[property][i], level));
         }
      },
      
      
      /**
       * Render a ForeignKey relationship as HTML, including controls
       * for adding / removing objects.
       * 
       * @param string property Object Property
       * @param object repr Data Representation
       */
      renderSet: function(property, title, allowedTypes) {
         var container = $('<div></div>'),
             heading = $('<h4></h4>'),
             self = this,
             i, item, rmButton, addButton, type, rmCallback, addCallback;
         
         container.addClass(property);
         container.addClass('objectSet');
         
         heading.html(title);
         heading.addClass('setHeader');
         container.append(heading);
         
         rmCallback = function() {
            var i = $(this).data('index'),
                item = self[property][i],
                msg = "Are you sure you want to delete this object? " + item.Type + "#" + (i + 1);
            
            if (confirm(msg)) {
               self[property].splice(i, 1);
               $(document).trigger('redrawQueryBuilder');
            }
         };
         
         addCallback = function() {
            var level = self.nlevel + 1;
            
            self[property].push(ViewsModel.instantiateType({
               Type: $(this).data('type')
            }, level));
            
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
         
         for (i = 0; i < allowedTypes.length; i++) {
            type = allowedTypes[i];
            addButton = $('<span class="addButton">Add ' + ViewsModel.getType(type).prototype.Name + '</span>');
            addButton.attr('title', ViewsModel.getType(type).prototype.Help);
            addButton.data('type', type);
            addButton.click(addCallback);
            
            container.append(addButton);
         }
         return container;
      },
      
      
      /**
       * Create a JSON serializable representation of this object.
       * 
       * @return object
       */
      repr: function() {
         return {
            Type: this.Type
         };
      },
      
      
      /**
       * Represent a ForeignKey relationship as JSON seriablizable object.
       * 
       * @return object
       */
      reprSet: function(property) {
         var set = [],
             i;
         
         for (i = 0; i < this[property].length; i++) {
            set.push(this[property][i].repr());
         }
         
         return set;
      }
   };
   
   
   /**
    * Client side model to represent a QueryResultsRetreiver object
    * 
    * @param object repr Object Data Representation
    * @param integer nlevel Nesting Level. All children of this object become nlevel + 1
    */
   QueryResultsRetriever = function(repr, nlevel) {
      ViewsModel.call(this, repr, nlevel);
      
      this.RootPredicate = ViewsModel.instantiateType(repr.RootPredicate, this.nlevel + 1);
      this.instantiateSet('Sorts', repr);
   };
   
   QueryResultsRetriever.prototype = $.extend({}, ViewsModel.prototype, {
      Type: 'QueryResultsRetriever',
      Name: 'Query Editor',
      RootPredicate: null,
      Sorts: [],
      
      
      /**
       * {@link ViewsModel.html}
       */
      html: function() {
         var resultsRetrieverContainer = ViewsModel.prototype.html.call(this),
             rootPredicateContainer = $('<div class="RootPredicate"></div>'),
             querySortContainer;
         
         rootPredicateContainer.append(this.RootPredicate.html());
         resultsRetrieverContainer.append(rootPredicateContainer);
         
         querySortContainer = this.renderSet('Sorts', 'Sorting', ['QuerySort']);
         resultsRetrieverContainer.append(querySortContainer);
         
         return resultsRetrieverContainer;
      },
      
      
      /**
       * {@link ViewsModel.repr}
       */
      repr: function() {
         var structure = ViewsModel.prototype.repr.call(this);
         
         structure.RootPredicate = this.RootPredicate.repr();
         structure.Sorts = this.reprSet('Sorts');
         return structure;
      }
   });
   
   
   /**
    * Client side model to represent a QuerySort object
    * 
    * @param object repr Object Data Representation
    * @param integer nlevel Nesting Level. All children of this object become nlevel + 1
    */
   QuerySort = function(repr, nlevel) {
      ViewsModel.call(this, repr, nlevel);
      
      this.FieldName = repr.FieldName;
      this.IsAscending = !!repr.IsAscending;
   };
   
   QuerySort.prototype = $.extend({}, ViewsModel.prototype, {
      Type: 'QuerySort',
      Name: 'Sort Clause',
      Help: 'Use a sort clause object to sort results based on the value of a field, in either ascending or descending order.',
      FieldName: '',
      IsAscending: false,
      
      
      /**
       * {@link ViewsModel.html}
       */
      html: function() {
         var querySortContainer = ViewsModel.prototype.html.call(this),
             fieldName = this.buildTextInput('FieldName', 'Field Name ("TableName".ColumnName)'),
             isAscending = this.buildBoolSelect('IsAscending', 'Order', 'Ascending', 'Descending');
         
         querySortContainer.append(fieldName);
         querySortContainer.append(isAscending);
         return querySortContainer;
      },
      
      
      /**
       * {@link ViewsModel.repr}
       */
      repr: function() {
         var structure = ViewsModel.prototype.repr.call(this);
         
         structure.FieldName = this.FieldName;
         structure.IsAscending = this.IsAscending;
         return structure;
      }
   });
   
   
   /**
    * Client side model to represent a QueryPredicate object
    * 
    * @param object repr Object Data Representation
    * @param integer nlevel Nesting Level. All children of this object become nlevel + 1
    */
   QueryPredicate = function(repr, nlevel) {
      ViewsModel.call(this, repr, nlevel);
      
      this.instantiateSet('PredicateConditions', repr);
   };
   
   QueryPredicate.prototype = $.extend({}, ViewsModel.prototype, {
      Type: 'QueryPredicate',
      Name: '',
      Help: '',
      PredicateConditions: [],
      
      
      /**
       * {@link ViewsModel.html}
       */
      html: function() {
         var queryPredicateContainer = ViewsModel.prototype.html.call(this),
             predicateConditionsContainer;
         
         predicateConditionsContainer = this.renderSet('PredicateConditions', 'Filter Conditions', [
            'CompoundPredicateCondition',
            'QueryParamPredicateCondition'
         ]);
         
         queryPredicateContainer.append(predicateConditionsContainer);
         return queryPredicateContainer;
      },
      
      
      /**
       * {@link ViewsModel.repr}
       */
      repr: function() {
         var structure = ViewsModel.prototype.repr.call(this);
         
         structure.PredicateConditions = this.reprSet('PredicateConditions');
         return structure;
      }
   });
   
   
   /**
    * Client side model to represent a CompoundPredicate object
    * 
    * @param object repr Object Data Representation
    * @param integer nlevel Nesting Level. All children of this object become nlevel + 1
    */
   CompoundPredicate = function(repr, nlevel) {
      QueryPredicate.call(this, repr, nlevel);
      
      this.IsConjunctive = !!repr.IsConjunctive;
      this.instantiateSet('Predicates', repr);
   };
   
   CompoundPredicate.prototype = $.extend({}, QueryPredicate.prototype, {
      Type: 'CompoundPredicate',
      Name: 'Compound Filter',
      Help: 'Use a compound filter to combine multiple filters together using either a logical AND or OR condition.',
      IsConjunctive: false,
      Predicates: [],
      
      
      /**
       * {@link ViewsModel.html}
       */
      html: function() {
         var queryPredicateContainer = QueryPredicate.prototype.html.call(this),
             isConjunctive = this.buildBoolSelect('IsConjunctive', 'Comparison Operator', 'Logical AND', 'Logical OR'),
             predicatesContainer;
         
         predicatesContainer = this.renderSet('Predicates', 'Filters', [
            'CompoundPredicate',
            'FieldPredicate',
            'TaxonomyTermPredicate'
         ]);
         
         predicatesContainer.find('h4:first').after(isConjunctive);
         queryPredicateContainer.append(predicatesContainer);
         return queryPredicateContainer;
      },
      
      
      /**
       * {@link ViewsModel.repr}
       */
      repr: function() {
         var structure = QueryPredicate.prototype.repr.call(this);
         
         structure.IsConjunctive = !!this.IsConjunctive;
         structure.Predicates = this.reprSet('Predicates');
         return structure;
      }
   });
   
   
   /**
    * Client side model to represent a FiledPredicate object
    * 
    * @param object repr Object Data Representation
    * @param integer nlevel Nesting Level. All children of this object become nlevel + 1
    */
   FieldPredicate = function(repr, nlevel) {
      QueryPredicate.call(this, repr, nlevel);
      
      this.FieldName = repr.FieldName;
      this.Qualifier = repr.Qualifier;
      this.IsRawSQL = repr.IsRawSQL;
      
      this.instantiateSet('Values', repr);
   };
   
   FieldPredicate.prototype = $.extend({}, QueryPredicate.prototype, {
      Type: 'FieldPredicate',
      Name: 'Field Filter',
      Help: 'Use a field filter object to filter returned results by comparing the value of a saved field to a set of given values.',
      FieldName: '',
      Qualifier: '',
      QualifierOptions: ['gt', 'gte', 'lt', 'lte', 'equals', 'notequal', 'like', 'in', 'notin'],
      IsRawSQL: false,
      Values: [],
      
      
      /**
       * {@link ViewsModel.html}
       */
      html: function() {
         var fieldPredicateContainer = QueryPredicate.prototype.html.call(this),
             fieldName = this.buildTextInput('FieldName', 'Field Name ("TableName".ColumnName)'),
             qualifier = this.buildSelectInput('Qualifier', 'Comparison Operator', this.QualifierOptions),
             isRawSQL = this.buildBoolSelect('IsRawSQL', 'Are Values Executable SQL?'),
             valuesContainer;
         
         fieldPredicateContainer.append(fieldName);
         fieldPredicateContainer.append(qualifier);
         fieldPredicateContainer.append(isRawSQL);
         
         valuesContainer = this.renderSet('Values', 'Values', ['FieldPredicateValue']);
         valuesContainer.removeClass('objectSet');
         fieldPredicateContainer.append(valuesContainer);
         
         return fieldPredicateContainer;
      },
      
      
      /**
       * {@link ViewsModel.repr}
       */
      repr: function() {
         var structure = QueryPredicate.prototype.repr.call(this);
         
         structure.FieldName = this.FieldName;
         structure.Qualifier = this.Qualifier;
         structure.IsRawSQL = this.IsRawSQL;
         structure.Values = this.reprSet('Values');
         return structure;
      }
   });
   
   
   /**
    * Client side model to represent a TaxonomyTermPredicate object
    * 
    * @param object repr Object Data Representation
    * @param integer nlevel Nesting Level. All children of this object become nlevel + 1
    */
   TaxonomyTermPredicate = function(repr, nlevel) {      
      QueryPredicate.call(this, repr, nlevel);
      
      this.Inclusive = repr.Inclusive;
      this.VocabTerm = repr.VocabTerm;
      this.Options = repr.Options;
   };
   
   TaxonomyTermPredicate.prototype = $.extend({}, QueryPredicate.prototype, {
      Type: 'TaxonomyTermPredicate',
      Name: 'Taxonomy Term Filter',
      Help: 'Use a taxonomy term filter to either include or exclude all returned results with the a supplied taxonomy term.',
      Inclusive: false,
      VocabTerm: '',
      Options: [],
      
      
      /**
       * {@link ViewsModel.html}
       */
      html: function() {
         var taxonomyTermPredicateContainer = QueryPredicate.prototype.html.call(this),
             inclusive = this.buildBoolSelect('Inclusive', 'Items with this Term are: ', 'Included in Results', 'Excluded from Results'),
             vocabTerm = this.buildTextInput('VocabTerm', 'Vocabulary Term (VocabularyMachineName.TermMachineName)');
         
         taxonomyTermPredicateContainer.append(vocabTerm);
         taxonomyTermPredicateContainer.append(inclusive);
         return taxonomyTermPredicateContainer;
      },
      
      
      /**
       * {@link ViewsModel.repr}
       */
      repr: function() {
         var structure = QueryPredicate.prototype.repr.call(this);
         structure.Type = 'TaxonomyTermPredicate';
         structure.Inclusive = this.Inclusive;
         structure.VocabTerm = this.VocabTerm;
         structure.Options = this.Options;
         return structure;
      }
   });
   
   
   /**
    * Client side model to represent a PredicateCondition object
    * 
    * @param object repr Object Data Representation
    * @param integer nlevel Nesting Level. All children of this object become nlevel + 1
    */
   PredicateCondition = function(repr, nlevel) {
      ViewsModel.call(this, repr, nlevel);
   };
   
   PredicateCondition.prototype = $.extend({}, ViewsModel.prototype, {
      Type: 'PredicateCondition',
      Name: '',
      Help: ''
   });
   
   
   /**
    * Client side model to represent a CompoundPredicateCondition object
    * 
    * @param object repr Object Data Representation
    * @param integer nlevel Nesting Level. All children of this object become nlevel + 1
    */
   CompoundPredicateCondition = function(repr, nlevel) {
      PredicateCondition.call(this, repr, nlevel);
      
      this.IsConjunctive = !!repr.IsConjunctive;
      this.instantiateSet('Conditions', repr);
   };
   
   CompoundPredicateCondition.prototype = $.extend({}, PredicateCondition.prototype, {
      Type: 'CompoundPredicateCondition',
      Name: 'Compound Filter Condition',
      Help: 'Use a compound filter condition to group multiple filter conditions together using either a logical AND or OR comparison.',
      IsConjunctive: false,
      Conditions: [],
      
      
      /**
       * {@link ViewsModel.html}
       */
      html: function() {
         var compoundPredicateConditionContainer = PredicateCondition.prototype.html.call(this),
             isConjunctive = this.buildBoolSelect('IsConjunctive', 'Comparison Operator', 'Logical AND', 'Logical OR'),
             conditionsContainer;
         
         conditionsContainer = this.renderSet('Conditions', 'Conditions', [
            'CompoundPredicateCondition',
            'QueryParamPredicateCondition'
         ]);
         
         compoundPredicateConditionContainer.append(isConjunctive);
         compoundPredicateConditionContainer.append(conditionsContainer);
         return compoundPredicateConditionContainer;
      },
      
      
      /**
       * {@link ViewsModel.repr}
       */
      repr: function() {
         var structure = PredicateCondition.prototype.repr.call(this);
         
         structure.IsConjunctive = this.IsConjunctive;
         structure.Conditions = this.reprSet('Conditions');
         return structure;
      }
   });
   
   
   /**
    * Client side model to represent a QueryParamPredicateCondition object
    * 
    * @param object repr Object Data Representation
    * @param integer nlevel Nesting Level. All children of this object become nlevel + 1
    */
   QueryParamPredicateCondition = function(repr, nlevel) {
      PredicateCondition.call(this, repr, nlevel);
      
      this.QueryParamName = repr.QueryParamName;
      this.PresenceRequired = !!repr.PresenceRequired;
   };
   
   QueryParamPredicateCondition.prototype = $.extend({}, PredicateCondition.prototype, {
      Type: 'QueryParamPredicateCondition',
      Name: 'Query Parameter Filter Condition',
      Help: 'Use a query parameter filter condition to selectively apply a filter based on the presence of a URL query parameter.',
      QueryParamName: '',
      PresenceRequired: false,
      
      
      /**
       * {@link ViewsModel.html}
       */
      html: function() {
         var queryParamPredicateCondition = PredicateCondition.prototype.html.call(this),
             queryParamName = this.buildTextInput('QueryParamName', 'Query Parameter Name'),
             presenceRequired = this.buildBoolSelect('PresenceRequired', 'Require the Query Parameter to be Present?');
         
         queryParamPredicateCondition.append(queryParamName);
         queryParamPredicateCondition.append(presenceRequired);
         return queryParamPredicateCondition;
      },
      
      
      /**
       * {@link ViewsModel.repr}
       */
      repr: function() {
         var structure = PredicateCondition.prototype.repr.call(this);
         
         structure.QueryParamName = this.QueryParamName;
         structure.PresenceRequired = this.PresenceRequired;
         return structure;
      }
   });
   
   
   /**
    * Client side model to represent a FieldPredicateValue object
    * 
    * @param object repr Object Data Representation
    * @param integer nlevel Nesting Level. All children of this object become nlevel + 1
    */
   FieldPredicateValue = function(repr, nlevel) {
      ViewsModel.call(this, repr, nlevel);
      
      this.Value = repr.Value;
   };
   
   FieldPredicateValue.prototype = $.extend({}, ViewsModel.prototype, {
      Type: 'FieldPredicateValue',
      Name: 'Value',
      Help: 'Use a value to supply a field filter with a value to compare against. This value can be either a constant, executable SQL, or a call to a dynamic value tokenizer (ex. $$CurrentPageID$$).',
      Value: '',
      
      
      /**
       * {@link ViewsModel.html}
       */
      html: function() {
         var fieldPredicateValueContainer = ViewsModel.prototype.html.call(this),
             value = this.buildTextInput('Value', 'Value');
         
         fieldPredicateValueContainer.append(value);
         return fieldPredicateValueContainer;
      },
      
      
      /**
       * {@link ViewsModel.repr}
       */
      repr: function() {
         var structure = ViewsModel.prototype.repr.call(this);
         
         structure.Value = this.Value;
         return structure;
      }
   });
   
   
   // Instantiate Query Editors and tie there edits to
   // the hidden JSON form they power.
   $(document).ready(function() {
      $('input.viewsQueryBuilderRepr').each(function() {
         var field = $(this),
             json = field.val(),
             repr = $.parseJSON(json),
             query = new QueryResultsRetriever(repr, 1),
             form = query.html(),
             ui = $(this).prev('div');
         
         if (!JSON || !JSON.stringify || true) {
            ui.html('<p>Browser must support JSON tools. Modern Chrome or Firefox is recommended.</p>');
         }
         
         ui.html(form);
         $('input, select').live('change', function() {
            var json = JSON.stringify(query.repr());
            field.val(json);
         });
         
         $(document).bind('redrawQueryBuilder', function() {
            var form = query.html(),
                json = JSON.stringify(query.repr());
            ui.html(form);
            field.val(json);
         });
      });
   });
   
}());
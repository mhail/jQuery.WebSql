/*! jQuery.WebSql | https://github.com/mhail/jQuery.WebSql */

(function($, window, undefined) {

		var resultExtensions = {
			'toArray' : function() {
				var a = [];
				for(var i=0; i<this.rows.length; i++) {
					a.push(this.rows.item(i));
				}
				return a;
			},
			'single' : function() {
				var row = this.rows.item(0);
				if (undefined === row) {
					return null;
				}
				for(var i in row) if (row.hasOwnProperty(i)) {
					return row[i];
				}
			}
		};

		window.WebSql = (function() {
			var WebSql = function(options) {
				if (this === window) {
				    return new WebSql(options);
				}
				this.constructor = WebSql;
				this.init.apply(this, arguments);
			};

			WebSql.resultExtensions = resultExtensions;

			$.extend(WebSql.prototype, {
				'init': function(options) {
					this.settings = $.extend( {
				      'name'        : 'db',
				      'version' 	: '1.0',
				      'display'		: 'Db',
				      'size'		: 1000000,
				      'debug'		: false
				    }, options);

					this.db = window.openDatabase(this.settings.name, this.settings.version, this.settings.display, this.settings.size);
				},
				'Sql' : {
					'insert' : function(table, record, replace) {
						var columns = [], p = [], values = [];
						for(var i in record) if (record.hasOwnProperty(i))
						{
							columns.push(i);
							p.push('?');
							values.push(record[i]);
						}
						
						var sql = ['INSERT', !!replace ? 'OR REPLACE' : '', 'INTO', table, '(', columns.join(', '), ')', 'VALUES', '(', p.join(', '), ')'].join(' ');	
						return [sql, values];	
					},
					'delete' : function(table, record, cols) {
						var values = [];
					
						var predicate = cols.map(function(col, i){
							values.push(record[col]);
							return [col, '=', '?'].join(' ');
						}).join(' AND ');
					
						var sql = ['DELETE FROM', table, 'WHERE', predicate].join(' ');
					
						return [sql, values];
					}
				},
				'transaction' : function() {
					var d = $.Deferred();
					
					this.db.transaction(d.resolve, d.reject);
					
					return d.promise(); 
				},
				'query' : function(sql, params) {
					var d = $.Deferred(), self = this;
					if (undefined === params) {
						params = [];
					}
					this.db.transaction(function(tx){
						if (true === !!self.settings.debug) {
							console.log(sql, params);
						}
						tx.executeSql(sql, params, function(tx, results) {
							$.extend(results, WebSql.resultExtensions);
							if (true === !!self.settings.debug) {
								console.log(results.toArray());
							}
							d.resolve(tx, results);
						});
					}, function(err){
						if (true === !!self.settings.debug) {
							console.log(err);
						}
						d.reject(err);
					});
				
					return d.promise(); 
				},
				'insert' : function(table, record, replace) {
					return this.query.apply(this, this.Sql['insert'].apply(this, arguments));
				},
				'delete' : function(table, record, cols) {
					return this.query.apply(this, this.Sql['delete'].apply(this, arguments));
				}

			});

			$.WebSql = function(options) { return new WebSql(options); };
		})();
	})(jQuery, window);
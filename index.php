<?php
error_reporting(E_ALL);

$data = array();

$datas = array('foo', 'bar', 'baz', 'qux');

for($i =0; $i< 500; $i++) {
	$data[] = array(
		'id' => $i,
		'status' => rand ( 0, 1),
		'modified' => strtotime('2012-11-30') - rand ( 1, 10000),
		'data' => $datas[rand(0, count($datas) - 1)],
	);
}


if (isset($_GET['data'])) {
	header('Content-type: application/json');
	exit(json_encode($data));
}

?>
<!DOCTYPE html>
<html>
  <head>
    <title>WebSql Sync Example</title>
    <META name="author" content="Matthew Hail">
	<script src="http://code.jquery.com/jquery-1.8.3.min.js"></script>
    <script src="http://cloud.github.com/downloads/wycats/handlebars.js/handlebars-1.0.rc.1.js"></script>
	<script src="websql.js"></script>
    
  </head>
  <body>
    <h1>Example synchronizing a local websql database from a json rest service</h1>
    <p>The purpose if this example is to sync a remote database to a local websql database and perform all tasks asynchronously.
    	The current data is displayed on page load. 
    	once thet data is synchronized, the list is refreshed with the new data.</p>
    <p>View the console to see the progress.</p>

	<script id="entry-template" type="text/x-handlebars-template">
		<li>
			{{id}} - {{data}} - {{modified}}
		</li>
	</script>
    <ul id="items">
    	
    </ul>
    <script type="text/javascript" charset="utf-8">
	(function($, window, undefined) {
	
		TestApp = (function(){
			var TestApp = function(){
				if (this === window) {
				    return new TestApp();
				}
				this.constructor = TestApp;
				this.init.apply(this, arguments);
			}

			var compileTemplates = function(templates) {
				var d = $.Deferred();
				setTimeout(function() {
					for(var i in templates) if (templates.hasOwnProperty(i)) {
						try {
							var template = $(templates[i]).html();
							templates[i] = Handlebars.compile(template);
						} catch(e) {
							d.reject(e);
						}
					}
					d.resolve();
				});
				return d.promise();
			}

			$.extend(TestApp.prototype, {
				'init' : function() {
					this.db = $.WebSql({'debug': true});
					this.state.checkSchema = this.checkSchema();
					this.state.syncDb = this.syncDb();
					this.ready = $.when(compileTemplates(this.templates), this.state.checkSchema);
				},
				'templates' : {
					'entry-template' : "#entry-template",
				},
				'state': {},

				'checkSchema' : function() {
					return this.db.query('CREATE TABLE IF NOT EXISTS data (id unique, data, modified, status)');
				},

				'getData' : function(max) {
					return $.ajax({
					  url: window.location,
					  data: { data: '1', modified: max},
					  dataType: 'json',
					}).promise();
				},
				'syncDb' : function(db) {
					var d = $.Deferred(), self = this;
					
					self.db.query("SELECT MAX(modified) max_modified FROM data")
					.fail(d.reject)
					.then(function(tx, results) {
						var max = results.single() || 0;
						
						self.getData(max)
						.fail(d.reject)
						.then(function(data){
							
							var updates = data.map(function(record){
								var action = record.status === 1 ? self.db.insert('data', record, true) : self.db.delete('data', record, ['id']);
								action.done(function(){
									d.notify(record);
								});
								return action;
							});
							
							$.when.apply(window, updates)
							.fail(d.reject)
							.then(d.resolve);
						});
					});
					
					return d.promise();
				},
				'displayData' : function() {
					var d = $.Deferred(), self = this;
					self.db.query("SELECT * FROM data")
					.fail(d.reject)
					.then(function(tx, results) {
						try {
							$("#items").html($.map(results.toArray(), self.templates['entry-template']).join(''));
						} catch (e) {
							d.reject(e);
						}
						d.resolve();
					});
					return d.promise();
				}
			});

			return TestApp;
		})();
		
		$(function(){
			var app = TestApp();
			app.ready.fail(function(e) { console.log(e)});
			app.displayData().fail(function(e) { console.log(e)});

			//app.state.syncDb.progress(function(e) { console.log(e)});

			// display records
			app.state.syncDb.then(function(){ 
				app.displayData();
			});
		});

    })(jQuery, window);
    </script>
  </body>
</html>
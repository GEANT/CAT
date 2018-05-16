/* 
 * this code is copied from https://openlayers.org/en/latest/examples/custom-interactions.html
 * with minimal changes
 */


window.app = {};
var app = window.app;
app.Drag = function() {
  ol.interaction.Pointer.call(this, {
    handleDownEvent: app.Drag.prototype.handleDownEvent,
    handleDragEvent: app.Drag.prototype.handleDragEvent,
    handleMoveEvent: app.Drag.prototype.handleMoveEvent,
    handleUpEvent: app.Drag.prototype.handleUpEvent
  });

  this.coordinate_ = null;
  this.cursor_ = 'pointer';
  this.feature_ = null;
  this.previousCursor_ = undefined;
};

ol.inherits(app.Drag, ol.interaction.Pointer);

app.Drag.prototype.handleDownEvent = function(evt) {
  var map = evt.map;
  var feature = map.forEachFeatureAtPixel(evt.pixel,
      function(feature, layer) {
        return feature;
      },
      {layerFilter: app.Drag.prototype.layerFilter}
  );

  if (feature) {
    this.coordinate_ = evt.coordinate;
    this.feature_ = feature;
  }
  return !!feature;
};

app.Drag.prototype.handleDragEvent = function(evt) {
  var map = evt.map;

  var feature = map.forEachFeatureAtPixel(evt.pixel,
      function(feature, layer) {
        return feature;
      },
      {layerFilter: app.Drag.prototype.layerFilter});

  var deltaX = evt.coordinate[0] - this.coordinate_[0];
  var deltaY = evt.coordinate[1] - this.coordinate_[1];

  var geometry = /** @type {ol.geom.SimpleGeometry} */
      (this.feature_.getGeometry());
  geometry.translate(deltaX, deltaY);

  this.coordinate_[0] = evt.coordinate[0];
  this.coordinate_[1] = evt.coordinate[1];
};

app.Drag.prototype.handleMoveEvent = function(evt) {
  if (this.cursor_) {
    var map = evt.map;
    var feature = map.forEachFeatureAtPixel(evt.pixel,
        function(feature, layer) {
          return feature;
        },
        {layerFilter: app.Drag.prototype.layerFilter});
    var element = evt.map.getTargetElement();
    if (feature) {
      if (element.style.cursor != this.cursor_) {
        this.previousCursor_ = element.style.cursor;
        element.style.cursor = this.cursor_;
      }
    } else if (this.previousCursor_ !== undefined) {
      element.style.cursor = this.previousCursor_;
      this.previousCursor_ = undefined;
    }
  }
};

app.Drag.prototype.handleUpEvent = function(evt) {
  setTmpPointer(evt.coordinate);
  this.coordinate_ = null;
  this.feature_ = null;
  return false;
};

app.Drag.prototype.layerFilter = function(layer) {
    if (layer === tmpLayer)
              return(true);
          else
              return(false);
}



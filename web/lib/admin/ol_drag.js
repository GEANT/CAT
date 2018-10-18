/* 
 * this code based on https://openlayers.org/en/latest/examples/custom-interactions.html
 */

/** global: ol */
/** global: tmpLayer */

window.app = {};
var app = window.app;
app.Drag = function () {
    ol.interaction.Pointer.call(this, {
        handleDownEvent: handleDownEvent,
        handleDragEvent: handleDragEvent,
        handleMoveEvent: handleMoveEvent,
        handleUpEvent: handleUpEvent
    });

    this.coordinate_ = null;
    this.cursor_ = 'pointer';
    this.feature_ = null;
    this.previousCursor_ = undefined;
};

ol.inherits(app.Drag, ol.interaction.Pointer);

function handleDownEvent(evt) {
    var map = evt.map;
    var feature = map.forEachFeatureAtPixel(evt.pixel,
            basicFeatureHandler,
            {layerFilter: layerFilter}
    );
    if (feature) {
        this.coordinate_ = evt.coordinate;
        this.feature_ = feature;
    }
    return !!feature;
}

function handleDragEvent(evt) {
    var map = evt.map;
    var feature = map.forEachFeatureAtPixel(evt.pixel,
            basicFeatureHandler,
            {layerFilter: layerFilter});

    var deltaX = evt.coordinate[0] - this.coordinate_[0];
    var deltaY = evt.coordinate[1] - this.coordinate_[1];

    var geometry = /** @type {ol.geom.SimpleGeometry} */
            (this.feature_.getGeometry());
    geometry.translate(deltaX, deltaY);

    this.coordinate_[0] = evt.coordinate[0];
    this.coordinate_[1] = evt.coordinate[1];
}

function handleMoveEvent(evt) {
    if (this.cursor_) {
        var map = evt.map;
        var feature = map.forEachFeatureAtPixel(evt.pixel,
                basicFeatureHandler,
                {layerFilter: layerFilter});
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
}

function handleUpEvent(evt) {
    setTmpPointer(evt.coordinate);
    this.coordinate_ = null;
    this.feature_ = null;
    return false;
}

function layerFilter(layer) {
    if (layer === tmpLayer) {
        return(true);
    }
    return(false);
}

function basicFeatureHandler(feature, layer) {
    return(feature);
}



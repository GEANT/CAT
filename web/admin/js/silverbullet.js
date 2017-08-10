/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */

/**
 * Silverbullet pages library
 * @author Zilvinas Vaira
 */

/**
 * Silverbullet namespace
 * @namespace
 */

var silverbullet = {};

/**
 * Instantiates all views for Silverbullet application
 * 
 * @constructor Main Silverbullet application
 */
silverbullet.SilverbulletApplication = function (){
    /**
     * DatePicker []
     */
    this.datePickers = [];
    
};
/**
 * 
 * @param {SilverBullet.Views.DatePicker} datePicker
 */
silverbullet.SilverbulletApplication.prototype.addDatePicker = function (datePicker){
    this.datePickers [this.datePickers.length] = datePicker;
};

/**
 * 
 */
silverbullet.SilverbulletApplication.prototype.resetDates = function (){
    for ( var i = 0; i < this.datePickers.length;  i++) {
        this.datePickers[i].addDefaultValue();
    }
};

/**
 * 
 */
silverbullet.SilverbulletApplication.prototype.start = function(){
    
    //Create and render Calendar drop down element
    var calendarPool = new silverbullet.views.CalendarPool();
    calendarPool.render();
    
    //Create and render DatePicker elements
    var datePickerElements = document.getElementsByClassName(silverbullet.views.DatePicker.ELEMENT_CLASS);
    for ( var i = 0; i < datePickerElements.length; i++) {
        var datePicker = new silverbullet.views.DatePicker(datePickerElements[i], calendarPool);
        datePicker.render();
        this.addDatePicker(datePicker);
    }
    
    //Create and render Popup message if any
    var popupMessageElement = document.getElementById(silverbullet.views.PopupMessage.ELEMENT_CLASS);
    if(popupMessageElement){
        var popupMessage = new silverbullet.views.PopupMessage(popupMessageElement);
        popupMessage.render();
    }
    
    //Prepare compose email panel, prepare qr code panel and create all copy to clipboard elements
    var composeEmailPopupElement = document.getElementById(silverbullet.views.ComposeEmailPanel.ELEMENT_CLASS);
    var smsPopupElement = document.getElementById(silverbullet.views.SmsPanel.ELEMENT_CLASS);
    var qrCodePopupElement = document.getElementById(silverbullet.views.QrCodePanel.ELEMENT_CLASS);
    if(composeEmailPopupElement && qrCodePopupElement){
        var composeEmailPopup = new silverbullet.views.PopupMessage(composeEmailPopupElement, false);
        var composeEmailPanel = new silverbullet.views.ComposeEmailPanel(composeEmailPopup);
        composeEmailPanel.render();
        var smsPopup = new silverbullet.views.PopupMessage(smsPopupElement, false);
        var smsPanel = new silverbullet.views.SmsPanel(smsPopup);
        smsPanel.render();
        var qrCodePopup = new silverbullet.views.PopupMessage(qrCodePopupElement, false);
        var qrCodePanel = new silverbullet.views.QrCodePanel(qrCodePopup);
        qrCodePanel.render();
        var clipboardElements = document.getElementsByClassName(silverbullet.views.TokenProvider.ELEMENT_CLASS);
        for (var i = 0; i < clipboardElements.length; i++) {
            var clipboardElement = new silverbullet.views.TokenProvider(clipboardElements[i], composeEmailPanel, smsPanel, qrCodePanel);
            clipboardElement.render();
        }
    }
    
};

/**
 * Silverbullet Views namespace
 * @namespace
 */
silverbullet.views = {};


/**
 * Provides rendering and initialization interfaces
 * 
 * @constructor Defines abstract Silverbullet view class
 * @param {Element} element
 */
silverbullet.views.ViewElement = function(element){
    /**
     * @member {Element} element
     */
    this.element = element;
};
/**
 * Render view and its event handlers.
 * 
 * @abstract
 */
silverbullet.views.ViewElement.prototype.render = function (){
    throw new Error('must be implemented by subclass!');
};

/**
 * Wraps text input element with date picker features
 * 
 * @constructor Date picker input element
 * @extends silverbullet.views.ViewElement
 * @param {HTMLElement} element Text input element
 * @param {silverbullet.views.CalendarPool} calendarPool
 */
silverbullet.views.DatePicker = function(element, calendarPool){
    silverbullet.views.ViewElement.call(this, element);
    this.calendarPool = calendarPool;
    this.calendarPanel = calendarPool.getSelectedPanel(element.value, this);
    
    this.element.style.color = 'grey';
    
    this.originalValue = element.value;
    this.originalBackgroundColor = element.style.backgroundColor;
    
    this.validationDate = new Date();
    
    this.errorElement = document.createElement('div');
    this.errorElement.style.position = 'absolute';
    this.errorElement.style.zIndex = '5';
    this.errorElement.style.backgroundColor = 'white';
    this.errorElement.style.border = '1px solid grey';
    this.errorElement.style.display = 'block';
    this.errorTextElement = document.createElement('p');
    this.errorTextElement.style.margin = '5px';
    this.errorTextElement.style.color = 'red';
    this.errorTextElement.style.fontWeight = 'bold';
    
    this.errorElement.appendChild(this.errorTextElement);
    
    this.state = false;
    this.errorState = false;
    
    this.button = this.element.parentNode.getElementsByTagName('button')[0];
};
silverbullet.views.DatePicker.prototype = Object.create(silverbullet.views.ViewElement.prototype);
silverbullet.views.DatePicker.prototype.constructor = silverbullet.views.DatePicker;
silverbullet.views.DatePicker.ELEMENT_CLASS = 'sb-date-picker';

/**
 * 
 * @param {String} dateToken
 */
silverbullet.views.DatePicker.prototype.validateDate = function(dateToken){
    if(this.calendarPool.parseDate(this.validationDate, dateToken)){
        if(!this.state){
            this.state = true;
            this.element.style.backgroundColor = this.originalBackgroundColor;
            if(this.errorState){
                this.element.parentNode.removeChild(this.errorElement);
                this.errorState = false;
            }
        }
    }else{
        this.state = false;
        this.element.style.backgroundColor = 'pink';
        this.errorTextElement.innerHTML = "Must be a date!";
        this.element.parentNode.appendChild(this.errorElement);
        this.errorState = true;
    }
};

/**
 * 
 */
silverbullet.views.DatePicker.prototype.removeDefaultValue = function(){
    if(this.element.value == this.originalValue){
        this.element.value = '';
        this.element.style.color = 'black';
    }
};

/**
 * 
 */
silverbullet.views.DatePicker.prototype.addDefaultValue = function(){
    this.element.value = this.originalValue;
    this.element.style.color = 'grey';
};

/**
 * 
 * @returns {Boolean}
 */
silverbullet.views.DatePicker.prototype.isValid = function(){
    return this.state;
};

/**
 * 
 * @param {String} dateValue
 */
silverbullet.views.DatePicker.prototype.setDateValue = function(dateValue){
    this.element.value = dateValue;
};

/**
 * 
 */
silverbullet.views.DatePicker.prototype.togglePopup = function(){
    if(this.element.parentNode.contains(this.calendarPanel.element)){
        this.hidePopup();
    }else{
        this.showPopup();
    }
};

silverbullet.views.DatePicker.prototype.showPopup = function(){
    this.calendarPanel = this.calendarPool.getSelectedPanel(this.element.value, this);
    this.calendarPool.setCalendarPanel(this.calendarPanel);
    this.element.parentNode.appendChild(this.calendarPool.element);
};

silverbullet.views.DatePicker.prototype.hidePopup = function(){
    //if(this.element.parentNode.contains(this.calendarPanel.element)){
        this.element.parentNode.removeChild(this.calendarPool.element);
    //}
};

silverbullet.views.DatePicker.prototype.loadCurrentPanel = function(){
    var calendarPanel = this.calendarPool.getSelectedPanel('now', this);
    this.calendarPool.setCalendarPanel(calendarPanel);
};

silverbullet.views.DatePicker.prototype.loadPreviousPanel = function(){
    var calendarPanel = this.calendarPool.getPreviousPanel(this.calendarPool.calendarPanel);
    this.calendarPool.setCalendarPanel(calendarPanel);
};

silverbullet.views.DatePicker.prototype.loadNextPanel = function(){
    var calendarPanel = this.calendarPool.getNextPanel(this.calendarPool.calendarPanel);
    this.calendarPool.setCalendarPanel(calendarPanel);
};

/**
 * 
 */
silverbullet.views.DatePicker.prototype.render = function(){
    var that = this;
    this.element.addEventListener('focus', function() {
        that.removeDefaultValue();
    });
    this.element.addEventListener('blur', function() {
        if(that.element.value == that.originalValue || that.element.value == ''){
            that.addDefaultValue();
        }
        that.validateDate(that.element.value);
    });
    this.button.addEventListener('click', function() {
        that.togglePopup();
    });
    this.button.addEventListener('blur', function() {
        if(that.calendarPool.isMouseOut()){
            that.hidePopup();
        }
    });

};

/**
 * Provides a panel for date picker object with month and date controls
 * 
 * @constructor Popup calendar panel for Date picker
 * @extends silverbullet.views.ViewElement * @param {Number} year
 * @param {Number} month
 * @param {silverbullet.views.DatePicker} datePicker
 */
silverbullet.views.CalendarPanel = function(year, month, datePicker){
    silverbullet.views.ViewElement.call(this, document.createElement('div'));
    this.datePicker = datePicker;
    this.year = year;
    this.month = month;
    this.dates = [];
    this.mouseOverState = false;
    
};
silverbullet.views.CalendarPanel.prototype = Object.create(silverbullet.views.ViewElement.prototype);
silverbullet.views.CalendarPanel.prototype.constructor = silverbullet.views.CalendarPanel;

/**
 * 
 * @param {Number} month
 * @param {Number} date
 * @param {silverbullet.views.CalendarDate}
 */
silverbullet.views.CalendarPanel.prototype.addCalendarDate = function (month, date, calendarDate) {
    if(typeof this.dates[month] == 'undefined'){
        this.dates[month] = [];
    }
    this.dates[month][date] = calendarDate;
    this.element.appendChild(calendarDate.element);
};

/**
 * 
 */
silverbullet.views.CalendarPanel.prototype.getCalendarDate = function (month, date) {
    if (typeof this.dates[month] != 'undefined' && typeof this.dates[month][date] != 'undefined') {
        return this.dates[month][date];
    }else{
        return null;
    }
};

/**
 * 
 */
silverbullet.views.CalendarPanel.prototype.selectCalendarDate = function(dateValue){
    this.datePicker.setDateValue(dateValue);
    this.datePicker.hidePopup();
    this.datePicker.validateDate(dateValue);
    this.datePicker.calendarPool.mouseOverState = false;
};

/**
 * 
 */
silverbullet.views.CalendarPanel.prototype.setDatePicker = function(datePicker){
    this.datePicker = datePicker;
};

/**
 * 
 * @param {silverbullet.views.DatePicker} datePicker
 */
silverbullet.views.CalendarPanel.prototype.update = function (datePicker, sYear, sMonth, sDate, cYear, cMonth, cDate) {
    this.datePicker = datePicker;

    datePicker.calendarPool.setSelected(sYear, sMonth, sDate);
    datePicker.calendarPool.setCurrent(cYear, cMonth, cDate);

};

/**
 * 
 * @returns {Boolean}
 */
silverbullet.views.CalendarPanel.prototype.isMouseOut = function(){
    return !this.mouseOverState;
};

/**
 * 
 */
silverbullet.views.CalendarPanel.prototype.render = function(){
    var that = this;
    this.element.addEventListener('mouseover', function() {
        that.mouseOverState = true;
    });
    this.element.addEventListener('mouseout', function() {
        that.mouseOverState = false;
    });

};

/**
 * Provides single date element for calendar panel
 * 
 * @constructor Creates and references single date element
 * @param {Date} date
 * @param {silverbullet.views.CalendarPanel} calendarPanel
 */
silverbullet.views.CalendarDate = function (date, calendarPanel) {
    silverbullet.views.ViewElement.call(this, document.createElement('div'));
    this.dateValue = this.toFormatedDateString(date);
    this.calendarPanel = calendarPanel;
    this.originalColor = this.element.style.backgroundColor;
    
    this.element.style.display = 'inline-table';
    this.element.style.cursor = 'pointer';
    this.element.style.textAlign = 'center';
    this.element.style.width = '30px';
    this.element.style.borderBottom = '1px solid grey';
    this.element.innerHTML = date.getDate();
};
silverbullet.views.CalendarDate.prototype = Object.create(silverbullet.views.ViewElement.prototype);
silverbullet.views.CalendarDate.prototype.constructor = silverbullet.views.CalendarDate;

/**
 * 
 */
silverbullet.views.CalendarDate.prototype.toFormatedDateString = function (date) {
    var mm = date.getMonth() + 1;
    var dd = date.getDate();
    return date.getFullYear() + '-' + ((mm>9 ? '' : '0') + mm) + '-' + ((dd>9 ? '' : '0') + dd);
};

/**
 * 
 * @param {Date} currentDate
 */
silverbullet.views.CalendarDate.prototype.markCurrent = function () {
    this.element.style.fontWeight = 'bold';
};

/**
 * 
 */
silverbullet.views.CalendarDate.prototype.markSelected = function () {
    this.element.style.backgroundColor = '#BCD7E8';
};

/**
 * 
 */
silverbullet.views.CalendarDate.prototype.restoreCurrent = function () {
    this.element.style.fontWeight = 'normal';
};

/**
 * 
 */
silverbullet.views.CalendarDate.prototype.restoreSelected = function () {
    this.element.style.backgroundColor = 'white';
};

/**
 * 
 */
silverbullet.views.CalendarDate.prototype.render = function () {
    var that = this;
    this.element.addEventListener('mouseover', function () {
        that.originalColor = this.style.backgroundColor;
        this.style.backgroundColor = 'lightgrey';
    });
    this.element.addEventListener('mouseout', function () {
        this.style.backgroundColor = that.originalColor;
    });
    
    this.element.addEventListener('click', function () {
        this.style.backgroundColor = that.originalColor;
        that.calendarPanel.selectCalendarDate(that.dateValue);
    });

};

/**
 * Pool object that stores unique Calendar panels
 * 
 * @constructor 
 */
silverbullet.views.CalendarPool = function(){
    silverbullet.views.ViewElement.call(this, document.createElement('div'));
    this.element.style.position = 'absolute';
    this.element.style.zIndex = '10';
    //this.element.style.width = '300px';
    //this.element.style.height = '200px';
    this.element.style.backgroundColor = 'white';
    this.element.style.border = '1px solid grey';
    this.element.style.display = 'block';
    this.element.style.padding = '10px';
    this.element.setAttribute('tabindex', 0);
    
    this.controlsTitle;
    
    this.calendarPanel;

    this.mouseOverState = false;

    this.cYear;
    this.cMonth;
    this.cDate;
    this.sYear;
    this.sMonth;
    this.sDate;
    
    this.currentDate = new Date();
    this.selectedDate = new Date();
    this.calendarPanels = [];
    this.weekDays = ["Sun", "Mon", "Tue","Wed", "Thu","Fri","Sat"];
};
silverbullet.views.CalendarPool.prototype = Object.create(silverbullet.views.ViewElement.prototype);
silverbullet.views.CalendarPool.prototype.constructor = silverbullet.views.CalendarPool;

/**
 * 
 */
silverbullet.views.CalendarPool.prototype.setCalendarPanel = function (calendarPanel) {
    this.year = calendarPanel.year;
    this.month = calendarPanel.month;
    if(typeof this.calendarPanel != 'undefined'){
        this.element.removeChild(this.calendarPanel.element);
    }
    this.calendarPanel = calendarPanel;
    this.controlsTitle.innerHTML = calendarPanel.year + ', ' + (calendarPanel.month + 1);
    this.element.appendChild(calendarPanel.element);
};

/**
 * 
 * @returns {Boolean}
 */
silverbullet.views.CalendarPool.prototype.isMouseOut = function(){
    return !this.mouseOverState;
};

/**
 * 
 * @param {Number} year
 * @param {Numebr} month
 * @param {silverbullet.views.CalendarPanel} calendarPanel
 */
silverbullet.views.CalendarPool.prototype.buildCalendarDays = function (year, month, calendarPanel) {
    
    //Find first day in a panel
    this.selectedDate.setFullYear(year);
    this.selectedDate.setMonth(month);
    this.selectedDate.setDate(1);
    var day = this.selectedDate.getDay();
    if(day > 0){
        this.selectedDate.setDate(0);
        var lastDay = this.selectedDate.getDate();
        this.selectedDate.setDate(lastDay - day + 1);
    }

    //Build week days row
    for(var i = 0; i < 7; i++){
        var dayElement = document.createElement('div');
        dayElement.style.display = 'inline-table';
        dayElement.style.textAlign = 'center';
        dayElement.style.width = '30px';
        dayElement.style.backgroundColor = '#F0F0F0';
        dayElement.style.borderBottom = '1px solid grey';
        dayElement.innerHTML = this.weekDays[i];
        calendarPanel.element.appendChild(dayElement);
    }
    calendarPanel.element.appendChild(document.createElement('br'));
    
    //Build all day rows
    var startMonth = this.selectedDate.getMonth();
    while(startMonth != (month + 1) % 12){
        for(var j = 0; j < 7; j++){
            var calendarDate = new silverbullet.views.CalendarDate(this.selectedDate, calendarPanel);
                calendarDate.render();
                calendarPanel.addCalendarDate(this.selectedDate.getMonth(), this.selectedDate.getDate(), calendarDate);
            this.selectedDate.setDate(this.selectedDate.getDate() + 1);
        }
        calendarPanel.element.appendChild(document.createElement('br'));
        startMonth = this.selectedDate.getMonth();
    }
};

/**
 * 
 * @param {Number} year
 * @param {Number} month
 * @param {silverbullet.views.CalendarPanel}
 */
silverbullet.views.CalendarPool.prototype.addCalendarPanel = function (year, month, calendarPanel) {
    if(typeof this.calendarPanels[year] == 'undefined'){
        this.calendarPanels[year] = [];
    }
    this.calendarPanels[year][month] = calendarPanel;
};

/**
 * 
 */
silverbullet.views.CalendarPool.prototype.getCalendarPanel = function (year, month, datePicker) {
    var calendarPanel;
    if (typeof this.calendarPanels[year] == 'undefined' || typeof this.calendarPanels[year][month] == 'undefined') {
        calendarPanel = new silverbullet.views.CalendarPanel(year, month, datePicker);
            //this.buildCalendarControls(year + ', ' + (month + 1), calendarPanel);
            this.buildCalendarDays(year, month, calendarPanel);
        calendarPanel.render();
        this.addCalendarPanel(year, month, calendarPanel);
    }else{
        calendarPanel = this.calendarPanels[year][month];
    }
    return calendarPanel;
};

/**
 * 
 */
silverbullet.views.CalendarPool.prototype.setSelected = function(year, month, date){
    var calendarPanel;
    var calendarDate;
    if(year != this.sYear || month != this.sMonth || date != this.sDate){
        if(typeof this.sYear != 'undefined'){
            calendarPanel = this.getCalendarPanel(this.sYear, this.sMonth, this);
            calendarDate = calendarPanel.getCalendarDate(this.sMonth, this.sDate);
            calendarDate.restoreSelected();
        }
        calendarPanel = this.getCalendarPanel(year, month, this);
        calendarDate = calendarPanel.getCalendarDate(month, date);
        if(calendarDate != null){
            calendarDate.markSelected();
            this.sYear = year;
            this.sMonth = month;
            this.sDate = date;
        }
    }

};

/**
 * 
 */
silverbullet.views.CalendarPool.prototype.setCurrent = function(year, month, date){
    var calendarPanel;
    var calendarDate;
    if(year != this.cYear || month != this.cMonth || date != this.cDate){
        if(typeof this.cYear != 'undefined'){
            calendarPanel = this.getCalendarPanel(this.cYear, this.cMonth, this);
            calendarDate = calendarPanel.getCalendarDate(this.cMonth, this.cDate);
            calendarDate.restoreCurrent();
        }
        calendarPanel = this.getCalendarPanel(year, month, this);
        calendarDate = calendarPanel.getCalendarDate(month, date);
        if(calendarDate != null){
            calendarDate.markCurrent();
            this.cYear = year;
            this.cMonth = month;
            this.cDate = date;
        }
    }

};

silverbullet.views.CalendarPool.prototype.parseDate = function (dateObject, dateToken) {
    var dateArray = dateToken.split('-');
    dateObject.setFullYear(parseInt(dateArray[0]));
    var month = parseInt(dateArray[1])-1;
    dateObject.setMonth((month >= 0 && month < 12) ? month : NaN);
    var date = parseInt(dateArray[2]);
    dateObject.setDate((date >= 0 && date < 32) ? date : NaN);
    return !isNaN(dateObject.getDate()) && date == dateObject.getDate();
};

/**
 * 
 * @param {String} selectedDateToken
 * @param {silverbullet.views.DatePicker} datePicker
 */
silverbullet.views.CalendarPool.prototype.getSelectedPanel = function (selectedDateToken, datePicker) {
    
    //Parse current date 
    if(!this.parseDate(this.selectedDate, selectedDateToken)){
        this.selectedDate.setTime(Date.now());
    }
    
    //Get year and month values
    var sYear = this.selectedDate.getFullYear();
    var sMonth = this.selectedDate.getMonth();
    var sDate = this.selectedDate.getDate();
    
    var cYear = this.currentDate.getFullYear();
    var cMonth = this.currentDate.getMonth();
    var cDate = this.currentDate.getDate();
    
    //If panel not exists create one
    var calendarPanel = this.getCalendarPanel(sYear, sMonth, datePicker);
    
    //Update calendar panel
    calendarPanel.update(datePicker, sYear, sMonth, sDate, cYear, cMonth, cDate);
    this.controlsTitle.innerHTML = sYear + ', ' + (sMonth + 1);
    
    return calendarPanel;
    
};

/**
 * 
 */
silverbullet.views.CalendarPool.prototype.getPreviousPanel = function(calendarPanel) {
    this.selectedDate.setFullYear(calendarPanel.year);
    this.selectedDate.setMonth(calendarPanel.month);
    this.selectedDate.setDate(1);
    this.selectedDate.setMonth(calendarPanel.month-1);
    var previousPanel = this.getCalendarPanel(this.selectedDate.getFullYear(), this.selectedDate.getMonth(), calendarPanel.datePicker);
    previousPanel.setDatePicker(calendarPanel.datePicker);
    return previousPanel;
};

/**
 * 
 */
silverbullet.views.CalendarPool.prototype.getNextPanel = function(calendarPanel) {
    this.selectedDate.setFullYear(calendarPanel.year);
    this.selectedDate.setMonth(calendarPanel.month);
    this.selectedDate.setDate(1);
    this.selectedDate.setMonth(calendarPanel.month+1);
    var nextPanel = this.getCalendarPanel(this.selectedDate.getFullYear(), this.selectedDate.getMonth(), calendarPanel.datePicker);
    nextPanel.setDatePicker(calendarPanel.datePicker);
    return nextPanel;
};

/**
 * 
 */
silverbullet.views.CalendarPool.prototype.render = function() {
    //Preparing controls panel
    var that = this;
    var controlsPanel = document.createElement('div');
    controlsPanel.style.paddingBottom = '20px';
    controlsPanel.style.textAlign = 'right';
    
        //Adding title
        this.controlsTitle = document.createElement('p');
        this.controlsTitle.style.display = 'inline';
        this.controlsTitle.style.paddingRight = '40px';

    controlsPanel.appendChild(this.controlsTitle);
    
        //Adding buttons
        var previousButton = document.createElement('button')
        previousButton.setAttribute('type', 'button');
        previousButton.innerHTML = '<';
        previousButton.addEventListener('click', function() {
            that.calendarPanel.datePicker.loadPreviousPanel();
        });
        previousButton.addEventListener('blur', function() {
            if(that.isMouseOut()){
                that.calendarPanel.datePicker.hidePopup();
            }
        });
        controlsPanel.appendChild(previousButton);
        
        var currentButton = document.createElement('button')
        currentButton.setAttribute('type', 'button');
        currentButton.innerHTML = '●';
        currentButton.addEventListener('click', function() {
            that.calendarPanel.datePicker.loadCurrentPanel();
        });
        currentButton.addEventListener('blur', function() {
            if(that.isMouseOut()){
                that.calendarPanel.datePicker.hidePopup();
            }
        });
        controlsPanel.appendChild(currentButton);
        
        var nextButton = document.createElement('button')
        nextButton.setAttribute('type', 'button');
        nextButton.innerHTML = '>';
        nextButton.addEventListener('click', function() {
            that.calendarPanel.datePicker.loadNextPanel();
        });
        nextButton.addEventListener('blur', function() {
            if(that.isMouseOut()){
                that.calendarPanel.datePicker.hidePopup();
            }
        });
        controlsPanel.appendChild(nextButton);


    this.element.appendChild(controlsPanel);
    
    this.element.addEventListener('mouseover', function() {
        that.mouseOverState = true;
    });
    this.element.addEventListener('mouseout', function() {
        that.mouseOverState = false;
    });
    this.element.addEventListener('blur', function() {
        if(that.isMouseOut()){
            that.calendarPanel.datePicker.hidePopup();
        }
    });


};

/**
 * Handles popup messages.
 * 
 * @param {Element} element Any HTML element.
 * @param {Boolean} isDisposable Defines what happens when close button is clicked. If it is disposable element will be removed, otherwise it will be hidden.
 * @constructor
 */
silverbullet.views.PopupMessage = function (element, isDisposable = true) {
    silverbullet.views.ViewElement.call(this, element);
    
    /** @member {Boolean} */
    this.isDisposable = isDisposable;
    /** @member {Boolean} */
    this.isCentred = true;
    /** @member {HTMLCollection} */
    this.redirectButtons = document.getElementsByClassName(element.id + '-redirect');
    /** @member {HTMLCollection} */
    this.closeButtons = document.getElementsByClassName(element.id + '-close');
    /** @member {jQuery} */
    this.grayJQElement = $(element).find(".graybox");
    /** @member {jQuery} */
    this.containerJQElement = $(element).find(".containerbox");
};
silverbullet.views.PopupMessage.prototype = Object.create(silverbullet.views.ViewElement.prototype);
silverbullet.views.PopupMessage.prototype.constructor = silverbullet.views.PopupMessage;
silverbullet.views.PopupMessage.ELEMENT_CLASS = 'sb-popup-message';

/**
 * Shows popup message box and all of its contents.
 * 
 * @param {Number} width
 * @param {Boolean} positionToCenter
 */
silverbullet.views.PopupMessage.prototype.show = function (width = 0, positionToCenter = true) {
    this.element.style.display = 'block';
    this.isCentred = positionToCenter;
    if(width > 0){
        this.resize(width);
    }
    if(positionToCenter){
        this.positionToCenter();
    }
};

/**
 * Hides popup message box and all of its contents.
 */
silverbullet.views.PopupMessage.prototype.hide = function () {
    this.element.style.display = 'none';
};

/**
 * Removes popup message box and all of its contents from HTML tree.
 */
silverbullet.views.PopupMessage.prototype.dispose = function () {
    this.element.parentNode.removeChild(this.element);
};

/**
 * Calculates height of the message box positions it in the center of the browser window.
 */
silverbullet.views.PopupMessage.prototype.positionToCenter = function () {
    if(this.element.children.length > 0){
        var msgbox = this.element.children[1];
        if(msgbox.children.length > 0){
            var msglayout = msgbox.children[0];
            if(msglayout.children.length > 0){
                var graybox = msglayout.children[0];
                var top = window.innerHeight/2 - graybox.offsetHeight/2;
                msglayout.style.top = top + 'px';
                graybox.style.top = top + 'px';
            }
        }
    }
};

/**
 * Resizes the width of the popup message box, which otherwise is a fixed value.
 * 
 * @param {String} width
 */
silverbullet.views.PopupMessage.prototype.resize = function (width) {
    var grayElementWidth = parseInt(width) + 20;
    this.grayJQElement.css('width', grayElementWidth + 'px');
};

/**
 * Appends any jQuery element to the popup message contents.
 * 
 * @param {jQuery} jqElement
 */
silverbullet.views.PopupMessage.prototype.append = function (jqElement) {
    if(this.containerJQElement.find(jqElement).length==0){
        this.containerJQElement.append(jqElement);
    }
};

/**
 * Assigns event listeners to close buttons, redirect buttons and to a window resize in order to center a position of message box.
 */
silverbullet.views.PopupMessage.prototype.render = function () {
    var that = this;
    
    for(var i=0; i<this.closeButtons.length; i++){
        this.closeButtons[i].addEventListener('click', function () {
            if(that.isDisposable){
                that.dispose();
            }else{
                that.hide();
            }
        });
    }
    
    for(var j=0; j<this.redirectButtons.length; j++){
        this.redirectButtons[j].addEventListener('click', function () {
            that.dispose();
            var currentLocation = window.location.toString();
            window.location = currentLocation.replace("edit_silverbullet.php", "overview_idp.php");
        });
    }
    
    window.addEventListener('resize', function() {
        if(that.isCentred){
            that.positionToCenter();
        }
    })
    
};

/**
 * Finds and enables all invitation token input elements that need copy to clipboard and compose mail features.
 * 
 * @constructor
 * @param {Element} element
 * @param {silverbullet.views.ComposeEmailPanel} emailPanel
 * @param {silverbullet.views.SmsPanel} smsPanel
 * @param {silverbullet.views.QrCodePanel} qrcodePanel
 */
silverbullet.views.TokenProvider = function (element, emailPanel, smsPanel, qrcodePanel) {
    silverbullet.views.ViewElement.call(this, element.parentNode);
    /** @member {jQuery} */
    this.tokenJQInput = $(element);
    /** @member {jQuery} */
    this.jqElement = this.tokenJQInput.parent();
    /** @member {jQuery} */
    this.copyJQButton = null;
    /** @member {jQuery} */
    this.composeJQButton = null;
    /** @member {jQuery} */
    this.smsJQButton = null;
    /** @member {jQuery} */
    this.qrcodeJQButton = null;
    /** @member {silverbullet.views.ComposeEmailPanel} */
    this.emailPanel = emailPanel;
    /** @member {silverbullet.views.SmsPanel} */
    this.smsPanel = smsPanel;
    /** @member {silverbullet.views.QrCodePanel} */
    this.qrcodePanel = qrcodePanel;
    if(this.jqElement){
        var query = '.' + silverbullet.views.TokenProvider.ELEMENT_CLASS;
        this.copyJQButton = this.jqElement.find(query + '-copy');
        this.originalBackgroundColor = this.copyJQButton.css('background-color');
        this.originalColor = this.copyJQButton.css('color');
        this.composeJQButton = this.jqElement.find(query+'-compose');
        this.smsJQButton = this.jqElement.find(query+'-sms');
        this.qrcodeJQButton = this.jqElement.find(query+'-qrcode');
    }
};
silverbullet.views.TokenProvider.prototype = Object.create(silverbullet.views.ViewElement.prototype);
silverbullet.views.TokenProvider.prototype.constructor = silverbullet.views.TokenProvider;
silverbullet.views.TokenProvider.ELEMENT_CLASS = 'sb-invitation-token';

/**
 * 
 */
silverbullet.views.TokenProvider.prototype.animate = function () {
    this.copyJQButton.css('background-color', this.originalBackgroundColor);
    this.copyJQButton.css('color', this.originalColor);
    var tokenValue =  this.tokenJQInput.val();
    this.tokenJQInput.val("");
    this.tokenJQInput.val(tokenValue);
    this.tokenJQInput.blur();
};

/**
 * Implements automatic with for invitation link input element.
 */
silverbullet.views.TokenProvider.prototype.resizeInput = function () {
    var containerWidth = this.jqElement.css("width").replace("px", "");
    var inputWidth = (containerWidth - 500) * 100 / containerWidth;
    this.tokenJQInput.css("width", inputWidth + "%");
};

/**
 * 
 */
silverbullet.views.TokenProvider.prototype.render = function () {
    var that = this;
    if(this.tokenJQInput){
        this.copyJQButton.on('click', function() {
            that.copyJQButton.css('background-color', that.originalColor);
            that.copyJQButton.css('color', that.originalBackgroundColor);
            that.tokenJQInput.select();
            document.execCommand("copy");
            setTimeout(function() {
                that.animate();
            }, 100);
        });
    }
    if(this.composeJQButton){
        this.composeJQButton.on('click', function() {
            that.emailPanel.setInvitationLink(that.tokenJQInput.val());
            that.emailPanel.show();
        })
    }
    if(this.smsJQButton){
        this.smsJQButton.on('click', function() {
            that.smsPanel.setInvitationLink(that.tokenJQInput.val());
            that.smsPanel.show();
        })
    }
    if(this.qrcodeJQButton){
        this.qrcodeJQButton.on('click', function() {
            that.qrcodePanel.setInvitationLink(that.tokenJQInput.val());
            that.qrcodePanel.show();
        })
    }
    this.resizeInput();
    $(window).resize(function () {
        that.resizeInput();
    });

};

/**
 * An abstract type describing panels for buttons.
 * 
 * @param {silverbullet.views.PopupMessage} popup Panel needs to be wraped inside Popup Message.
 * @constructor
 */
silverbullet.views.TokenReceiverPanel = function (popup) {
    if(popup){
        silverbullet.views.ViewElement.call(this, popup.element);
    }
    /** @member {String} */
    this.popup = popup;
    /** @member {String} */
    this.link = '';
    /** @member {Element} */
    this.errorElement = document.createElement('p');
    this.errorElement.style.color = "red";
};
silverbullet.views.TokenReceiverPanel.prototype = Object.create(silverbullet.views.ViewElement.prototype);
silverbullet.views.TokenReceiverPanel.prototype.constructor = silverbullet.views.ComposeEmailPanel;
silverbullet.views.TokenReceiverPanel.COMMAND_VALIDATE_EMAIL = 'validateemailaddress';
silverbullet.views.TokenReceiverPanel.COMMAND_GET_DETAILS = 'gettokenemaildetails';

/**
 * Setts the invitation link for a panel.
 * 
 * @param {String} link Represents invitation link.
 */
silverbullet.views.TokenReceiverPanel.prototype.setInvitationLink = function (link) {
    this.link = link;
};

/**
 * Display a panel.
 * 
 * @abstract
 */
silverbullet.views.TokenReceiverPanel.prototype.show = function () {
    throw new Error('must be implemented by subclass!');
};

/**
 * Displays error message at the bottom of the popup message box.
 * 
 * @param {String} text
 * @param {String} color
 */
silverbullet.views.TokenReceiverPanel.prototype.showError = function (text, color = 'red') {
    this.errorElement.style.color = color;
    this.errorElement.textContent = text;
    if(!this.errorElement.parentNode){
        this.popup.append(this.errorElement);
    }
};

/**
 * Hides previously generated error message.
 */
silverbullet.views.TokenReceiverPanel.prototype.hideError = function () {
    if(this.errorElement.parentNode){
        this.errorElement.parentNode.removeChild(this.errorElement);
    }
};

/**
 * Generates compose email dialog panel.
 * 
 * @param {silverbullet.views.PopupMessage} popup Panel needs to be wraped inside Popup Message.
 * @constructor
 */
silverbullet.views.ComposeEmailPanel = function (popup) {
    silverbullet.views.TokenReceiverPanel.call(this, popup);
    
    /** @member {String} */
    this.subject = '';
    /** @member {String} */
    this.body = '';
    /** @member {Boolean} */
    this.ajaxBusy = false;
    /** @member {silverbullet.views.EmailValidationCommand} */
    this.ajaxBuffer = null;

    /** @member {jQuery} */
    this.emailClientButton = null;
    /** @member {jQuery} */
    this.emailCATButton = null;
    /** @member {jQuery} */
    this.emailTextInput = null;
    
    /**
     * Hidden token element
     * @member {Element}
     */
    this.tokenElement = document.createElement('input');
    this.tokenElement.setAttribute('type', 'hidden');
    this.tokenElement.setAttribute('name', 'tokenlink');
    
    if(this.popup){
        var query = '#' + silverbullet.views.ComposeEmailPanel.ELEMENT_CLASS;
        var jqElement = $(this.element);
        this.emailClientButton = jqElement.find(query + '-client');
        this.emailCATButton = jqElement.find(query + '-cat');
        this.emailTextInput = jqElement.find(query + '-email');
    }
};
silverbullet.views.ComposeEmailPanel.prototype = Object.create(silverbullet.views.TokenReceiverPanel.prototype);
silverbullet.views.ComposeEmailPanel.prototype.constructor = silverbullet.views.ComposeEmailPanel;
silverbullet.views.ComposeEmailPanel.ELEMENT_CLASS = 'sb-compose-email';

/**
 * 
 */
silverbullet.views.ComposeEmailPanel.prototype.show = function () {
    var that = this;
    var containerJQElement = this.emailClientButton.parent();
    this.init();
    this.emailTextInput.val('');
    this.popup.show();
    this.popup.resize(containerJQElement.width());
    
    this.tokenElement.setAttribute('value', this.link);
    this.emailClientButton.parent().append(this.tokenElement);
    if(containerJQElement.find(this.tokenElement).length==0){
        containerJQElement.append(this.tokenElement);
    }
    
    $.ajax({
        method: "POST",
        url: "inc/silverbullet.inc.php",
        data: {command: silverbullet.views.TokenReceiverPanel.COMMAND_GET_DETAILS, tokenlink: that.link}
    }).done(function(data) {
        var jqEmail = $(data).find('email');
        that.subject = jqEmail.attr('subject');
        that.body = jqEmail.text();
    }).fail(function() {
        that.errorElement.textContent = "Failed to compose message body and subject!";
    });
};

/**
 * Restores all elements to their defaults.
 */
silverbullet.views.ComposeEmailPanel.prototype.init = function (){
    this.ajaxBusy = false;
    this.hideError();
    this.emailTextInput.prop('disabled', false);
    this.emailCATButton.prop('disabled', true);
};

/**
 * Executes command from buffer if there is one and allows text entry but disables button.
 * 
 * @param {silverbullet.views.EmailValidationCommand} command
 */
silverbullet.views.ComposeEmailPanel.prototype.repeat = function (command){
    this.ajaxBusy = false;
    this.emailTextInput.prop('disabled', false);
    this.emailCATButton.prop('disabled', true);
    this.cleanAjaxBuffer(command);
};

/**
 * States that Ajax is busy with a command, disables button and enables text intput.
 */
silverbullet.views.ComposeEmailPanel.prototype.start = function (){
    this.ajaxBusy = true;
    this.emailTextInput.prop('disabled', false);
    this.emailCATButton.prop('disabled', true);
};

/**
 * States that Ajax is busy with a command, disables both button and text intput.
 */
silverbullet.views.ComposeEmailPanel.prototype.block = function (){
    this.ajaxBusy = true;
    this.emailTextInput.prop('disabled', true);
    this.emailCATButton.prop('disabled', true);
};

/**
 * States that Ajax is busy with a command, disables button, enables text intput and places text selection cursor.
 */
silverbullet.views.ComposeEmailPanel.prototype.unblock = function (){
    this.ajaxBusy = true;
    this.emailTextInput.prop('disabled', false);
    this.emailTextInput.select();
    var length = this.emailTextInput.val().length;
    this.emailTextInput[0].selectionStart = length;
    this.emailTextInput[0].selectionEnd = length;
};

/**
 * Restores all elements to their defaults and executes command from buffer.
 * 
 * @param {silverbullet.views.EmailValidationCommand} command
 */
silverbullet.views.ComposeEmailPanel.prototype.complete = function (command){
    this.ajaxBusy = false;
    this.hideError();
    this.emailTextInput.prop('disabled', false);
    this.emailCATButton.prop('disabled', false);
    this.cleanAjaxBuffer(command);
};

/**
 * Executes command from buffer.
 * 
 * @param {silverbullet.views.EmailValidationCommand} command
 */
silverbullet.views.ComposeEmailPanel.prototype.cleanAjaxBuffer = function (command){
    if(this.ajaxBuffer){
        if(this.ajaxBuffer.address != command.address){
            var command = this.ajaxBuffer;
            this.ajaxBuffer = null;
            this.receive(command);
        }else{
            this.ajaxBuffer = null;
        }
    }
};

/**
 * Accepts Ajax command to be executed. If Ajax is not busy executes command imediatly otherwise places it inside a buffer.
 * 
 * @param {silverbullet.views.EmailValidationCommand} command
 */
silverbullet.views.ComposeEmailPanel.prototype.receive = function (command) {
    if(!this.ajaxBusy){
        command.execute();
    }else{
        this.ajaxBuffer = command;
    }
};

/**
 * Applies email filter and initiates remote email validation once user enter '.' character.
 * 
 * @param {String} address
 * @param {Function} filter Optional value, should be a callback that receives event parameter and validates pressed key.
 * @param {KeyboardEvent} e Optional value, event object.
 */
silverbullet.views.ComposeEmailPanel.prototype.validateEmail = function(address, filter, e){
    if(address.indexOf('@') > 0){
        var parts = address.split('@');
        if(filter){
            if(!filter(e)){
                return;
            }
        }
        if(parts[1] && parts[1].indexOf('.') > 0){
            this.receive(new silverbullet.views.EmailValidationCommand(address, this));
        }else{
            this.init();
        }
    }else{
        this.init();
    }    
};

/**
 * Renders all popup and panel event handlers.
 */
silverbullet.views.ComposeEmailPanel.prototype.render = function () {
    var that = this;
    this.popup.render();
    this.emailClientButton.on('click', function() {
        var mailto = "mailto:";
        var address = that.emailTextInput.val();
        if(address!=""){
            mailto += address;
        }
        if(that.subject && that.body){
            mailto += "?subject=" + that.subject + "&body=" + encodeURI(that.body);
        }
        window.location.href = mailto;
    });
    this.emailTextInput.on('keyup', function(e) {
        var address = that.emailTextInput.val();
        that.validateEmail(address, function(e){
            return (e.which==8 || (e.which>45 && e.which<91) || (e.which>93 && e.which<112) || (e.which>185 && e.which<193) || (e.which>218 && e.which<223));
        }, e);
    });
    this.emailTextInput.on('input', function() {
        var address = that.emailTextInput.val();
        that.validateEmail(address);
    });
    
};

/**
 * Performs email validation. Can be placed in a buffer or queue while performing.
 * 
 * @constructor
 * @param {String} address
 * @param {silverbullet.views.ComposeEmailPanel} emailPanel

 */
silverbullet.views.EmailValidationCommand = function (address, emailPanel) {
    this.address = address;
    this.emailPanel = emailPanel;
    this.interval = -1;
    this.blocked = false;
};

/**
 * Clears timed execution of text input blocking, which was initiated if Ajax command significant amount of time.
 */
silverbullet.views.EmailValidationCommand.prototype.clear = function(){
    if(this.interval > 0){
        clearInterval(this.interval);
        this.interval = -1;
        if(this.blocked){
            this.blocked = false;
            this.emailPanel.unblock();
        }
    }
};

/**
 * Performs email validation, decides when to block/unblock button and text input for email.
 */
silverbullet.views.EmailValidationCommand.prototype.execute = function () {
    var that = this;
    this.emailPanel.start();
    
    var validationMessage = "Validating email '" + this.address + "'";
    this.interval = setInterval(function() {
        that.emailPanel.block();
        validationMessage += ".";
        that.emailPanel.showError(validationMessage, 'green');
    }, 1000);
    
    $.ajax({
        method: "POST",
        url: "inc/silverbullet.inc.php",
        data: {command: silverbullet.views.TokenReceiverPanel.COMMAND_VALIDATE_EMAIL, address: encodeURI(that.address)}
    }).done(function(data) {
        that.clear();
        
        var jqEmail = $(data).find('email');
        var isValid = jqEmail.attr('isValid');
        var message = jqEmail.text();

        if(isValid=='true'){
            that.emailPanel.complete(that);
            if(message.trim() == ''){
                that.emailPanel.showError("The mail domain is valid and emails will be transmitted securely.", 'green');
            }else{
                that.emailPanel.showError(message, 'GoldenRod'); //#DAA520
            }
        }else{
            that.emailPanel.repeat(that);
            if(message.trim() != ''){
                that.emailPanel.showError(message); 
            }else{
                that.emailPanel.showError("Email address '" + that.address + "' is not valid!");
            }
        }
        
    }).fail(function() {
        that.clear();
        that.emailPanel.repeat(that);
        that.emailPanel.showError("Failed to validate email address '" + that.address + "'!");
    });
    
};

/**
 * Generates QR code image panel. Panel needs to be wraped inside Popup Message.
 * 
 * @param {silverbullet.views.PopupMessage} popup Panel needs to be wraped inside Popup Message.
 * @constructor
 */
silverbullet.views.QrCodePanel = function (popup) {
    silverbullet.views.TokenReceiverPanel.call(this, popup);
    /** @member {jQuery} */
    this.imageElement = null;
    
    // Constructor
    if(this.popup){
        var query = '#' + silverbullet.views.QrCodePanel.ELEMENT_CLASS;
        var jqElement = $(this.element);
        this.imageElement = jqElement.find(query + '-image');
        this.errorElement.style.fontWeight = "bold";
    }
};
silverbullet.views.QrCodePanel.prototype = Object.create(silverbullet.views.TokenReceiverPanel.prototype);
silverbullet.views.QrCodePanel.prototype.constructor = silverbullet.views.QrCodePanel;
silverbullet.views.QrCodePanel.ELEMENT_CLASS = 'sb-qr-code';

/**
 * Retrieves QR image from server and shows it inside a popup.
 */
silverbullet.views.QrCodePanel.prototype.show = function () {
    var that = this;
    $.ajax({
        method: "POST",
        url: "inc/silverbullet.inc.php",
        data: {command: silverbullet.views.TokenReceiverPanel.COMMAND_GET_DETAILS, tokenlink: that.link}
    }).done(function(data) {
        var jqEmail = $(data).find('email');
        that.imageElement.attr('src', jqEmail.attr('image'));
        that.imageElement.css('float',"none");
        that.imageElement.css('cursor',"default");
        that.imageElement.show();
        that.hideError();
        that.popup.show(that.imageElement.attr('width'));
    }).fail(function() {
        that.imageElement.hide();
        that.showError("Could not generate QR code image!")
        that.popup.show(that.imageElement.attr('width'));
    });

};

/**
 * Renders popup events only.
 */
silverbullet.views.QrCodePanel.prototype.render = function () {
    this.popup.render();
};

/**
 * Generates send in SMS panel. Panel needs to be wraped inside Popup Message.
 * 
 * @constructor
 * @param {silverbullet.views.PopupMessage} popup Panel needs to be wraped inside Popup Message.
 */
silverbullet.views.SmsPanel = function (popup) {
    // Parent constructor
    silverbullet.views.TokenReceiverPanel.call(this, popup);
    
    /** @member {jQuery} */
    this.phoneJQElement = null;
    
    /**
     * Hidden token element
     * @member {Element}
     */
    this.tokenElement = document.createElement('input');
    this.tokenElement.setAttribute('type', 'hidden');
    this.tokenElement.setAttribute('name', 'tokenlink');
    
    if(this.popup){
        var query = '#' + silverbullet.views.SmsPanel.ELEMENT_CLASS;
        this.phoneJQElement = $(this.element).find(query + '-phone');
    }
};
silverbullet.views.SmsPanel.prototype = Object.create(silverbullet.views.TokenReceiverPanel.prototype);
silverbullet.views.SmsPanel.prototype.constructor = silverbullet.views.SmsPanel;
silverbullet.views.SmsPanel.ELEMENT_CLASS = 'sb-send-sms';

/**
 * Calculates popup size and shows popup.
 */
silverbullet.views.SmsPanel.prototype.show = function () {
    var containerJQElement = this.phoneJQElement.parent();
    this.phoneJQElement.val("");
    this.popup.show();
    this.popup.resize(containerJQElement.width());
    
    this.tokenElement.setAttribute('value', this.link);
    if(containerJQElement.find(this.tokenElement).length==0){
        containerJQElement.append(this.tokenElement);
    }
};

/**
 * Renders popup events only and attaches phone number value filter.
 */
silverbullet.views.SmsPanel.prototype.render = function () {
    var that = this;
    this.popup.render();
    this.phoneJQElement.on("keyup, input", function(e) {
        var value = that.phoneJQElement.val();
        var nonNumbers =/\D/g;
        if(nonNumbers.test(value)){
            var start = that.phoneJQElement[0].selectionStart;
            var end = that.phoneJQElement[0].selectionEnd;
            var length = value.length; 
            value = value.replace(nonNumbers, "");
            var delta = value.length - length;
            that.phoneJQElement.val(value);
            that.phoneJQElement[0].selectionStart = start + delta;
            that.phoneJQElement[0].selectionEnd = end + delta;
        }
    })
};


/// <reference path="../../js/jquery.d.ts" />
/// <reference path="../../js/actor-manager.ts" />
var AmeActorSelector = /** @class */ (function () {
    function AmeActorSelector(actorManager, isProVersion, allOptionEnabled) {
        var _this = this;
        if (allOptionEnabled === void 0) { allOptionEnabled = true; }
        this.selectedActor = null;
        this.selectedDisplayName = 'All';
        this.visibleUsers = [];
        this.subscribers = [];
        this.isProVersion = false;
        this.allOptionEnabled = true;
        this.cachedVisibleActors = null;
        this.isDomInitStarted = false;
        this.actorManager = actorManager;
        if (typeof isProVersion !== 'undefined') {
            this.isProVersion = isProVersion;
        }
        this.allOptionEnabled = allOptionEnabled;
        this.currentUserLogin = wsAmeActorSelectorData.currentUserLogin;
        this.visibleUsers = wsAmeActorSelectorData.visibleUsers;
        this.ajaxParams = wsAmeActorSelectorData;
        //Discard any users that don't exist / were not loaded by the actor manager.
        var _ = AmeActorSelector._;
        this.visibleUsers = _.intersection(this.visibleUsers, _.keys(actorManager.getUsers()));
        jQuery(function () {
            _this.initDOM();
        });
    }
    AmeActorSelector.prototype.initDOM = function () {
        var _this = this;
        if (this.isDomInitStarted) {
            return;
        }
        this.isDomInitStarted = true;
        this.selectorNode = jQuery('#ws_actor_selector');
        this.populateActorSelector();
        //Don't show the selector in the free version.
        if (!this.isProVersion) {
            this.selectorNode.hide();
            return;
        }
        //Select an actor on click.
        this.selectorNode.on('click', 'li a.ws_actor_option', function (event) {
            var href = jQuery(event.target).attr('href');
            var fragmentStart = href.indexOf('#');
            var actor = null;
            if (fragmentStart >= 0) {
                actor = href.substring(fragmentStart + 1);
            }
            if (actor === '') {
                actor = null;
            }
            _this.setSelectedActor(actor);
            event.preventDefault();
        });
        //Display the user selection dialog when the user clicks "Choose users".
        this.selectorNode.on('click', '#ws_show_more_users', function (event) {
            event.preventDefault();
            AmeVisibleUserDialog.open({
                currentUserLogin: _this.currentUserLogin,
                users: _this.actorManager.getUsers(),
                visibleUsers: _this.visibleUsers,
                actorManager: _this.actorManager,
                save: function (userDetails, selectedUsers) {
                    _this.actorManager.addUsers(userDetails);
                    _this.visibleUsers = selectedUsers;
                    //The user list has changed, so clear the cache.
                    _this.cachedVisibleActors = null;
                    //Display the new actor list.
                    _this.populateActorSelector();
                    //Save the user list via AJAX.
                    _this.saveVisibleUsers();
                }
            });
        });
    };
    AmeActorSelector.prototype.setSelectedActor = function (actorId) {
        if ((actorId !== null) && !this.actorManager.actorExists(actorId)) {
            return;
        }
        var previousSelection = this.selectedActor;
        this.selectedActor = actorId;
        this.highlightSelectedActor();
        if (actorId !== null) {
            this.selectedDisplayName = this.actorManager.getActor(actorId).getDisplayName();
        }
        else {
            this.selectedDisplayName = 'All';
        }
        //Notify subscribers that the selection has changed.
        if (this.selectedActor !== previousSelection) {
            for (var i = 0; i < this.subscribers.length; i++) {
                this.subscribers[i](this.selectedActor, previousSelection);
            }
        }
    };
    AmeActorSelector.prototype.onChange = function (callback) {
        this.subscribers.push(callback);
    };
    AmeActorSelector.prototype.highlightSelectedActor = function () {
        //Set up and populate the selector element if we haven't done that yet.
        if (!this.isDomInitStarted) {
            this.initDOM();
        }
        //Deselect the previous item.
        this.selectorNode.find('.current').removeClass('current');
        //Select the new one or "All".
        var selector;
        if (this.selectedActor === null) {
            selector = 'a.ws_no_actor';
        }
        else {
            selector = 'a[href$="#' + this.selectedActor + '"]';
        }
        this.selectorNode.find(selector).addClass('current');
    };
    AmeActorSelector.prototype.populateActorSelector = function () {
        var actorSelector = this.selectorNode, $ = jQuery;
        var isSelectedActorVisible = false;
        //Build the list of available actors.
        actorSelector.empty();
        if (this.allOptionEnabled) {
            actorSelector.append('<li><a href="#" class="current ws_actor_option ws_no_actor" data-text="All">All</a></li>');
        }
        var visibleActors = this.getVisibleActors();
        for (var i = 0; i < visibleActors.length; i++) {
            var actor = visibleActors[i], name_1 = this.getNiceName(actor);
            actorSelector.append($('<li></li>').append($('<a></a>')
                .attr('href', '#' + actor.getId())
                .attr('data-text', name_1)
                .text(name_1)
                .addClass('ws_actor_option')));
            isSelectedActorVisible = (actor.getId() === this.selectedActor) || isSelectedActorVisible;
        }
        if (this.isProVersion) {
            var moreUsersText = 'Choose users\u2026';
            actorSelector.append($('<li>').append($('<a></a>')
                .attr('id', 'ws_show_more_users')
                .attr('href', '#more-users')
                .attr('data-text', moreUsersText)
                .text(moreUsersText)));
        }
        if (this.isProVersion) {
            actorSelector.show();
        }
        //If the selected actor is no longer on the list, select the first available option instead.
        if ((this.selectedActor !== null) && !isSelectedActorVisible) {
            if (this.allOptionEnabled) {
                this.setSelectedActor(null);
            }
            else {
                var availableActors = this.getVisibleActors();
                this.setSelectedActor(AmeActorSelector._.first(availableActors).getId());
            }
        }
        this.highlightSelectedActor();
    };
    AmeActorSelector.prototype.repopulate = function () {
        this.cachedVisibleActors = null;
        this.populateActorSelector();
    };
    AmeActorSelector.prototype.getVisibleActors = function () {
        var _this = this;
        if (this.cachedVisibleActors) {
            return this.cachedVisibleActors;
        }
        var _ = AmeActorSelector._;
        var actors = [];
        //Include all roles.
        //Idea: Sort roles either alphabetically or by typical privilege level (admin, editor, author, ...).
        _.forEach(this.actorManager.getRoles(), function (role) {
            actors.push(role);
        });
        //Include the Super Admin (multisite only).
        if (this.actorManager.getUser(this.currentUserLogin).isSuperAdmin) {
            actors.push(this.actorManager.getSuperAdmin());
        }
        //Include the current user.
        actors.push(this.actorManager.getUser(this.currentUserLogin));
        //Include other visible users.
        _(this.visibleUsers)
            .without(this.currentUserLogin)
            .sortBy()
            .forEach(function (login) {
            var user = _this.actorManager.getUser(login);
            actors.push(user);
        })
            .value();
        this.cachedVisibleActors = actors;
        return actors;
    };
    AmeActorSelector.prototype.saveVisibleUsers = function () {
        jQuery.post(this.ajaxParams.adminAjaxUrl, {
            'action': this.ajaxParams.ajaxUpdateAction,
            '_ajax_nonce': this.ajaxParams.ajaxUpdateNonce,
            'visible_users': JSON.stringify(this.visibleUsers)
        });
    };
    AmeActorSelector.prototype.getCurrentUserActor = function () {
        return this.actorManager.getUser(this.currentUserLogin);
    };
    AmeActorSelector.prototype.getNiceName = function (actor) {
        var name = actor.getDisplayName();
        if (actor.hasOwnProperty('userLogin')) {
            var user = actor;
            if (user.userLogin === this.currentUserLogin) {
                name = 'Current user (' + user.userLogin + ')';
            }
            else {
                name = user.getDisplayName() + ' (' + user.userLogin + ')';
            }
        }
        return name;
    };
    /**
     * Wrap the selected actor ID in a computed observable so that it can be used with Knockout.
     * @param ko
     */
    AmeActorSelector.prototype.createKnockoutObservable = function (ko) {
        var _this = this;
        var internalObservable = ko.observable(this.selectedActor);
        var publicObservable = ko.computed({
            read: function () {
                return internalObservable();
            },
            write: function (newActor) {
                _this.setSelectedActor(newActor);
            }
        });
        this.onChange(function (newSelectedActor) {
            internalObservable(newSelectedActor);
        });
        return publicObservable;
    };
    AmeActorSelector.prototype.createIdObservable = function (ko) {
        return this.createKnockoutObservable(ko);
    };
    AmeActorSelector.prototype.createActorObservable = function (ko) {
        var _this = this;
        var internalObservable = ko.observable((this.selectedActor === null) ? null : this.actorManager.getActor(this.selectedActor));
        var publicObservable = ko.computed({
            read: function () {
                return internalObservable();
            },
            write: function (newActor) {
                _this.setSelectedActor((newActor !== null) ? newActor.getId() : null);
            }
        });
        var self = this;
        this.onChange(function (newSelectedActor) {
            if (newSelectedActor === null) {
                internalObservable(null);
            }
            else {
                internalObservable(self.actorManager.getActor(newSelectedActor));
            }
        });
        return publicObservable;
    };
    AmeActorSelector._ = wsAmeLodash;
    return AmeActorSelector;
}());
//# sourceMappingURL=actor-selector.js.map
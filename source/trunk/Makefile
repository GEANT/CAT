VERSION = CAT-1.1-alpha1
VV = $(VERSION)/
.PHONY: translation

all: documentation translation

documentation:
	# rm -R doc/*
	phpdoc -i *.inc -it @assert -d core,config,devices,tutorials -t web/doc -dn "Configuration" -ti "IEEE 802.1X Configuration Assistant Tool (CAT) Documentation" -o HTML:Smarty:HandS
	cp web/resources/images/consortium_logo.png web/doc/media/logo.png

translation:
	echo "****************************************"
	echo "*** Generating templates from source ***"
	echo "****************************************"
	xgettext --from-code=UTF-8 --add-comments=/ -L php -d core core/*.php -o translation/core.pot
	xgettext --from-code=UTF-8 --add-comments=/ -L php -d device devices/*.php devices/apple_mobileconfig/*.php devices/ms/*.php devices/ms/Files/*.inc devices/ms/Files/xp/*.inc devices/linux/Linux.php devices/test_module/*.php -o translation/devices.pot
	xgettext --from-code=UTF-8 --add-comments=/ -L php -d web_admin web/admin/*.php web/admin/inc/*.php -o translation/web_admin.pot
	xgettext --from-code=UTF-8 --add-comments=/ -L php -d web_user web/user/*.php web/user/inc/*.php web/user/js/*.php web/*.php -o translation/web_user.pot
	for lang in `find translation/ -maxdepth 1 -mindepth 1 -type d | grep -v .svn`; do \
		echo "********************************************"; \
                echo "*** Now translating in $$lang ***"; \
		echo "********************************************"; \
		for area in web_admin web_user devices core ; do \
			msgmerge -q -v -U $$lang/$$area.po translation/$$area.pot; \
			msgfmt $$lang/$$area.po -o $$lang/LC_MESSAGES/$$area.mo; \
			done; \
	done; \
	echo "********************"; \
	echo "*** Syntax check ***"; \
	echo "********************"; \
	for lang in `find translation/ -maxdepth 1 -mindepth 1 -type d | grep -v .svn`; do \
		for area in web_admin web_user devices core ; do \
			msgfmt --check-header --check-domain $$lang/$$area.po; \
		done; \
        done; \
	rm messages.mo


distribution: all
	find . -name \*.po~ -exec rm {} \;
	mv web/resources/inc/footer.php web/resources/inc/footer_orig.php
	cat web/resources/inc/footer_orig.php | sed s/'RELEASE = "THERELEASE"'/'RELEASE = "$(VERSION)"'/ > web/resources/inc/footer.php
	rm web/resources/inc/footer_orig.php
	mv web/basic.php web/basic_orig.php
	cat web/basic_orig.php | sed s/'RELEASE = "THERELEASE"'/'RELEASE = "$(VERSION)"'/ > web/basic.php
	rm web/basic_orig.php
	mv web/index.php web/index_orig.php
	cat web/index_orig.php | sed s/'RELEASE = "THERELEASE"'/'RELEASE = "$(VERSION)"'/ > web/index.php
	rm web/index_orig.php
	rm -R -f NewFolder nbproject config/config.php devices/devices.php generic-data*
	tar -cvjf ../$(VERSION).tar.bz2 --show-transformed-names --exclude-vcs --xform 's/^\.\(\/\)/$(VERSION)\1/' .

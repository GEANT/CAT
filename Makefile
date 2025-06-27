VERSION = CAT-2.1.3
WLANSetEAPUserData = devices/ms/Files/WLANSetEAPUserData
.PHONY: translation

all: translation WLANSetEAPUserData documentation

documentation:
	rm -Rf web/apidoc build 
	phpDocumentor.phar run --force -d . -i core/phpqrcode.php -i core/PHPMailer -i tests -i core/simpleSAMLphp -i core/PHPUnit -i core/GeoIP2 -i vendor -i .git --template=clean --visibility=public --visibility=protected --visibility=private -t web/apidoc/ --title "CAT - The IEEE 802.1X Configuration Assistant Tool" 

pull_from_transifex:
	tx pull --all --force

translation: pull_from_transifex translation_source lang_translations

translation_source:
	echo "****************************************"
	echo "*** Generating templates from source ***"
	echo "****************************************"
	xgettext --from-code=UTF-8 --add-comments=/ -c -L php core/diag/*.php web/skins/*/diag/*.php web/diag/*.php web/skins/*/diag/js/*.php -o translation/diagnostics.pot
	xgettext --from-code=UTF-8 --add-comments=/ -c -L php core/*.php core/common/*.php web/lib/common/*.php -o translation/core.pot
	xgettext --from-code=UTF-8 --add-comments=/ -c -L php devices/*.php devices/*/*.php devices/ms/Files/*.inc -o translation/devices.pot
	xgettext --from-code=UTF-8 --add-comments=/ -c -L php web/admin/*.php web/admin/inc/*.php web/lib/admin/*.php -o translation/web_admin.pot
	xgettext --from-code=UTF-8 --add-comments=/ -c -L php web/user/*.php web/*.php web/lib/user/*.php -o translation/web_user.pot
	xgettext --from-code=UTF-8 --add-comments=/ -c -L php --join-existing web/skins/*/*.php web/skins/*/accountstatus/*.php web/skins/modern/user/*.php web/skins/modern/user/js/*.php -o translation/web_user.pot

lang_translations:
	for lang in `find translation/ -maxdepth 1 -mindepth 1 -type d | grep -v .git`; do \
		echo "********************************************"; \
                echo "*** Now translating in $$lang ***"; \
		echo "********************************************"; \
		for area in web_admin web_user devices core diagnostics ; do \
			mkdir -p $$lang/LC_MESSAGES; \
			msgmerge -q -v -U $$lang/$$area.po translation/$$area.pot; \
			msgfmt $$lang/$$area.po -o $$lang/LC_MESSAGES/$$area.mo; \
			done; \
	done; \
	echo "********************"; \
	echo "*** Syntax check ***"; \
	echo "********************"; \
	for lang in `find translation/ -maxdepth 1 -mindepth 1 -type d | grep -v .git`; do \
		for area in web_admin web_user devices core diagnostics ; do \
			msgfmt --check-header --check-domain $$lang/$$area.po; \
		done; \
        done; \
	rm messages.mo

WLANSetEAPUserData:
	rm -rf ${WLANSetEAPUserData}
	mkdir ${WLANSetEAPUserData}
	curl -o ${WLANSetEAPUserData}/WLANSetEAPUserData-1.1.zip -L https://github.com/rozmansi/WLANSetEAPUserData/releases/download/1.1/WLANSetEAPUserData-1.1.zip
	curl -o ${WLANSetEAPUserData}/WLANSetEAPUserData-1.1-src.tar.gz -L https://github.com/rozmansi/WLANSetEAPUserData/archive/refs/tags/1.1.tar.gz
	cd ${WLANSetEAPUserData}; unzip WLANSetEAPUserData-1.1.zip
	mv ${WLANSetEAPUserData}/ARM64/WLANSetEAPUserData.exe ${WLANSetEAPUserData}/WLANSetEAPUserDataARM64.exe
	mv ${WLANSetEAPUserData}/Win32/WLANSetEAPUserData.exe ${WLANSetEAPUserData}/WLANSetEAPUserData32.exe
	mv ${WLANSetEAPUserData}/x64/WLANSetEAPUserData.exe ${WLANSetEAPUserData}/WLANSetEAPUserData64.exe
	rmdir ${WLANSetEAPUserData}/ARM64 ${WLANSetEAPUserData}/Win32 ${WLANSetEAPUserData}/x64
	rm ${WLANSetEAPUserData}/WLANSetEAPUserData-1.1.zip

distribution: all
	git submodule update --init devices/ms/Files/GEANTLink
	find . -name \*.po~ -exec rm {} \;
	find . -name svn-commit.tmp -exec rm {} \;
	rm -R -f build vendor phpcs.xml NewFolder nbproject .tx config/config-master.php config/config-confassistant.php config/config-diagnostics.php devices/Devices.php .codeclimate.yml .git .github .scrutinizer.yml .gitignore .gitmodules core/simpleSAMLphp core/PHPMailer core/PHPUnit core/GeoIP2
	tar -cvjf ../$(VERSION).tar.bz2 --show-transformed-names --exclude-vcs --xform 's/^\.\(\/\)/$(VERSION)\1/' .


PKG_NAME = comment-chero
TARGETS = en_US tr_TR
MSGFMT = msgfmt -v
MSGSRC = $(wildcard *.php includes/*.php)
XGETTEXT = xgettext --keyword=__ --keyword=_e --default-domain=$(PKG_NAME) --language=php # −−package-name=$(PKG_NAME)
POT_DIR = languages
POT_TARGET = $(POT_DIR)/$(PKG_NAME).pot


%.mo: %.po
	$(MSGFMT) $*.po -o $*.mo

all: updatepot

updatepot:
	-$(XGETTEXT) $(MSGSRC) -o $(POT_TARGET)

clean:
	-rm -rf $(POT_DIR)/comment-chero*.mo


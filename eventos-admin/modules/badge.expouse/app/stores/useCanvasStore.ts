import { defineStore } from "pinia";
import { usePageStore } from "./usePageStore";
interface Size {
  width: number;
  height: number;
}

interface Variant {
  inner: string;
  marker: string;
  pixel: string;
}

interface QRCode {
  value: string;
  variant: string;
  radius: number;
  blackColor: string;
  whiteColor: string;
}

interface Position {
  left: number;
  top: number;
}

interface Avatar {
  showBorder: boolean;
  showRing: boolean;
  shape: string;
  radius: number;
  customClipPath: string;
  containerStyle: any;
  avatar_src: string;
  imageStyle: any;
}

interface ElementProperties {
  size: Size;
  rotation: number;
  font: string;
  fontWeight: string;
  fontStyle: string;
  fontSize: string | number;
  fillColor: string;
  fillTransparency: boolean;
  imagePosition: string;
  objectFit: string;

  textDecoration: string;
  color: string;
  textAlign: string;
  verticalAlign: string;
  horizontalAlign: string;
  textTransform: string;
  src: string;
  strokeColor: string;
  strokeWidth: number;
  associatedData: string;
  content: string;
  x: number;
  y: number;
  text: string;
  displayOption?: string;
  qrcode: QRCode;
  direction: string;
  avatar: Avatar;
}

interface CanvasElement {
  id: string | number;
  text: string;
  key: string;
  type: string;
  label: string;
  position: Position;
  properties: ElementProperties;
  isSelected: boolean;
  isDragging: boolean;
  visible: boolean;
}

export const useCanvasStore = defineStore("canvasStore", {
  state: () => ({
    activeTab: "design" as string,
    frontBoxes: [] as CanvasElement[],
    backBoxes: [] as CanvasElement[],
    activeSide: "front" as "front" | "back",
    selectedElement: null as string | number | null,
    selectedElementType: null as string | null,
    currentProperties: {} as Partial<ElementProperties>,
    dropzone: null as HTMLElement | null,
    showImageModal: false,
    showGradientModal: false,
    showColorModal: false,
    frontBackground: null as string | null, // Background for front side
    backBackground: null as string | null, // Background for back side
    pendingImagePosition: null as Position | null,
    pendingImageSide: null as "front" | "back" | null,
    cursorPosition: null as { node: Node; offset: number } | null,
    dropdownOpen: false as boolean,
    imageItem: null as string | any,
    punchArea: false,
    punchCircle: "" as string,
    punchLong: "" as string,
    avatarSize: 150,
    avatarRadius: 32,
    avatarShape: "rounded",
    avatarCustomClipPath: "",
    avatarShowBorder: false,
    avatarShowRing: false,
  }),
  getters: {
    boxes: (state): CanvasElement[] =>
      state.activeSide === "front" ? state.frontBoxes : state.backBoxes,
    currentBackground: (state): string | null =>
      state.activeSide === "front"
        ? state.frontBackground
        : state.backBackground,
    getAvatarContainerStyle(state) {
      const base = {};
      let style = {};
      switch (state.avatarShape) {
        case "circle":
          style = { borderRadius: "9999px" };
          break;
        case "rounded":
          style = { borderRadius: `${state.avatarRadius}px` };
          break;
        case "squircle":
          style = {
            borderRadius: `${Math.min(state.avatarRadius, 100)}% / ${Math.min(
              state.avatarRadius + 10,
              100
            )}%`,
          };
          break;
        case "diamond":
          style = { clipPath: "polygon(50% 0, 100% 50%, 50% 100%, 0 50%)" };
          break;
        case "hex":
          style = {
            clipPath:
              "polygon(25% 5%, 75% 5%, 100% 50%, 75% 95%, 25% 95%, 0 50%)",
          };
          break;
        case "triangle":
          style = { clipPath: "polygon(50% 0, 0 100%, 100% 100%)" };
          break;
        case "blob":
          style = {
            clipPath:
              'path("M74.7 12.9c11.8 7.3 20.2 20 23 34.2 2.7 14.2-.3 29.9-8.6 39.8-8.3 9.9-21.8 14-35.6 12.2-13.8-1.7-27.8-10.3-35.1-22.8-7.3-12.5-7.8-28.8-2.5-41.4 5.3-12.6 16.4-21.5 28.4-25C56.2 6.6 68.9 5.7 74.7 12.9z")',
          };
          break;
        case "custom":
          style = state.avatarCustomClipPath
            ? { clipPath: state.avatarCustomClipPath }
            : {};
          break;
        default:
          style = { borderRadius: `${state.avatarRadius}px` };
      }
      return { ...base, ...style };
    },
    getAvatarImageStyle(state) {
      return {
        width: "100%",
        height: "100%",
        display: "block",
        objectFit: "cover",
      };
    },
  },
  actions: {
    computeContainerStyle(avatar: Avatar) {
      const {
        shape = "circle",
        radius = 32,
        customClipPath = "",
        showBorder,
        showRing,
      } = avatar;
      let base: any = {
        overflow: "hidden",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        backgroundColor: "#f3f4f6",
      };

      let shadows = ["0 1px 2px 0 rgba(0, 0, 0, 0.05)"];
      if (showRing) {
        const offsetWidth = 2;
        const ringWidth = 2;
        const offsetColor = "#ffffff";
        const ringColor = "#9ca3af";
        shadows.push(`0 0 0 ${offsetWidth}px ${offsetColor}`);
        shadows.push(`0 0 0 ${offsetWidth + ringWidth}px ${ringColor}`);
      }
      base.boxShadow = shadows.join(", ");

      if (showBorder) {
        base.borderWidth = "1px";
        base.borderStyle = "solid";
        base.borderColor = "#d1d5db";
      }

      let style = {};
      switch (shape) {
        case "circle":
          style = { borderRadius: "9999px" };
          break;
        case "rounded":
          style = { borderRadius: `${radius}px` };
          break;
        case "squircle":
          style = {
            borderRadius: `${Math.min(radius, 100)}% / ${Math.min(
              radius + 10,
              100
            )}%`,
          };
          break;
        case "diamond":
          style = { clipPath: "polygon(50% 0, 100% 50%, 50% 100%, 0 50%)" };
          break;
        case "hex":
          style = {
            clipPath:
              "polygon(25% 5%, 75% 5%, 100% 50%, 75% 95%, 25% 95%, 0 50%)",
          };
          break;
        case "triangle":
          style = { clipPath: "polygon(50% 0, 0 100%, 100% 100%)" };
          break;
        case "blob":
          style = {
            clipPath:
              'path("M74.7 12.9c11.8 7.3 20.2 20 23 34.2 2.7 14.2-.3 29.9-8.6 39.8-8.3 9.9-21.8 14-35.6 12.2-13.8-1.7-27.8-10.3-35.1-22.8-7.3-12.5-7.8-28.8-2.5-41.4 5.3-12.6 16.4-21.5 28.4-25C56.2 6.6 68.9 5.7 74.7 12.9z")',
          };
          break;
        case "custom":
          style = customClipPath ? { clipPath: customClipPath } : {};
          break;
        default:
          style = { borderRadius: `${radius}px` };
      }
      return { ...base, ...style };
    },
    computeImageStyle() {
      return {
        width: "100%",
        height: "100%",
        display: "block",
        objectFit: "cover",
      };
    },
    syncAvatarToCurrent() {
      if (this.selectedElementType !== "avatar") return;
      this.currentProperties.size = {
        width: this.avatarSize,
        height: this.avatarSize,
      };
      this.currentProperties.avatar = {
        ...this.currentProperties.avatar,
        shape: this.avatarShape,
        radius: this.avatarRadius,
        customClipPath: this.avatarCustomClipPath,
        showBorder: this.avatarShowBorder,
        showRing: this.avatarShowRing,
      };
      this.updateProperties(this.currentProperties);
    },
    setCavasElementData(canvasData: any) {
      console.log("setCavasElementData called with:", canvasData);
      const pageStore = usePageStore();
      if (!canvasData) return;
      if (canvasData.data.badge_json) {
        pageStore.$state = {
          ...pageStore.$state,
          ...canvasData.data.badge_json.page_config,
        };
        this.$state = { ...this.$state, ...canvasData.data.badge_json };
      } else {
        pageStore.$state = {
          ...pageStore.$state,
          ...canvasData.data.badge_json.page_config,
        };
        this.$state = { ...this.$state, ...canvasData };
      }
    },
    elementMachanism(data?: any) {
      const item = data.item;
      const position = data.position;

      const newElement: CanvasElement = {
        id: Date.now(),
        text: item.value,
        type: item.type,
        key: item.key,
        label: `${item.label}`,
        position,
        properties: {
          size: { width: data.width ?? "", height: data.height ?? "" },
          rotation: 0,
          font: "",
          fontWeight: "normal",
          fontStyle: "normal",
          fontSize: "Auto",
          fillColor: item.type === "rectangle" ? "blue" : "transparent",
          fillTransparency: false,
          imagePosition: "center" as string,
          objectFit: "cover" as string,

          textDecoration: "none",
          color: "black",
          textAlign: item.type === "h1" || item.type === "p" ? "center" : "",
          verticalAlign:
            item.type === "h1" || item.type === "p" ? "middle" : "",
          horizontalAlign:
            item.type === "h1" || item.type === "p" ? "center" : "",
          textTransform: "none",
          src: data.dataUrl ?? "",
          strokeColor: "",
          strokeWidth: 0,
          associatedData: "",
          content: "",
          x: position.left,
          y: position.top,
          text: item.label || "Sample Text",
          displayOption: "both sides",
          qrcode: data.qrcode ?? "",
          direction: "ltr",
          avatar: {
            showBorder: false,
            showRing: false,
            shape: "circle",
            radius: 32,
            customClipPath: "",
            avatar_src:
              "https://ui-avatars.com/api/?background=c8c9ca&color=6c757d&size=200",
            containerStyle: {},
            imageStyle: {},
          },
        },
        isSelected: true,
        isDragging: false,
        visible: true,
      };
      if (newElement.type === "avatar") {
        newElement.properties.avatar.containerStyle =
          this.computeContainerStyle(newElement.properties.avatar);
        newElement.properties.avatar.imageStyle = this.computeImageStyle();
      }
      return newElement;
    },
    addElementFromDrag(item: any, position: Position) {
      // Ensure position is within drop zone
      // if (item.type == "qrcode") {
      //   this.handleQRCodeGenerator("Attendee");
      //   // return false;
      // }
      const pageStore = usePageStore();
      const canvasWidth =
        this.dropzone?.offsetWidth || pageStore.presetWidth * 3.78;
      const canvasHeight =
        this.dropzone?.offsetHeight || pageStore.presetHeight * 3.78;
      const elementWidth = 200; // Default width
      const elementHeight = 64; // Default height

      const adjustedPosition = {
        left: Math.max(0, Math.min(position.left, canvasWidth - elementWidth)),
        top: Math.max(0, Math.min(position.top, canvasHeight - elementHeight)),
      };

      const data = {
        item: item,
        position: adjustedPosition,
        width:
          item.type == "avatar" || item.type == "qrcode" ? 150 : elementWidth,
        height:
          item.type == "avatar" || item.type == "qrcode" ? 150 : elementHeight,
        qrcode: {},
      };

      if (item.type == "qrcode") {
        data.qrcode = {
          value: "QRCode",
          variant: "pixelated",
          radius: 1,
          blackColor: "#000000", // 'var(--ui-text-highlighted)' if you are using `@nuxt/ui` v3
          whiteColor: "transparent",
        };
      }

      const newElement = this.elementMachanism(data);

      this.addElement(newElement);
      this.selectedElement = newElement.id;
      this.updateProperties();
    },

    handleImageUploaded(dataUrl: string) {
      // if (item.type === "img" || item.type === "background") {
      //   this.imageItem = item;
      //   this.showImageModal = true;
      const position = { left: 123, top: 204 };
      this.pendingImagePosition = position;
      this.pendingImageSide = this.activeSide;
      //   return;
      // }

      const pageStore = usePageStore();
      const customPosition = {
        left: 198,
        top: 277,
      };
      const canvasWidth =
        this.dropzone?.offsetWidth || pageStore.presetWidth * 3.78;
      const canvasHeight =
        this.dropzone?.offsetHeight || pageStore.presetHeight * 3.78;
      const elementWidth =
        this.imageItem.type === "background"
          ? pageStore.presetWidth * 3.78
          : 150;
      const elementHeight =
        this.imageItem.type === "background"
          ? pageStore.presetHeight * 3.78
          : 150;

      // Ensure position is within drop zone
      const adjustedPosition = {
        left: Math.max(
          0,
          Math.min(
            this.imageItem.type === "background"
              ? customPosition.left
              : this.pendingImagePosition.left,
            canvasWidth - elementWidth
          )
        ),
        top: Math.max(
          0,
          Math.min(
            this.imageItem.type === "background"
              ? customPosition.top
              : this.pendingImagePosition.top,
            canvasHeight - elementHeight
          )
        ),
      };

      const data = {
        item: {
          text: this.imageItem.label,
          type: this.imageItem.type,
          key: this.imageItem.type === "background" ? "background_img" : "img",
          label: this.imageItem.type === "background" ? "background" : "Image",
        },
        position: adjustedPosition,
        dataUrl: dataUrl,
        width: elementWidth,
        height: elementHeight,
      };

      const newElement = this.elementMachanism(data);

      if (this.pendingImageSide === "front") {
        this.frontBoxes.push(newElement);
      } else {
        this.backBoxes.push(newElement);
      }
      this.pendingImagePosition = null;
      this.pendingImageSide = null;
      this.selectedElement = newElement.id;
      this.updateProperties();
    },
    addElement(element: CanvasElement) {
      const boxes =
        this.activeSide === "front" ? this.frontBoxes : this.backBoxes;
      boxes.push(element);
    },

    handleQRCodeGenerator(qrcodeValue: string) {
      const qrcodeData = {
        value: qrcodeValue,
        variant: "pixelated",
        radius: 1,
        blackColor: "#000000", // 'var(--ui-text-highlighted)' if you are using `@nuxt/ui` v3
        whiteColor: "transparent",
      };
      const data = {
        item: {
          text: "QRCode",
          type: "qrcode",
          key: "qrcode",
          label: "QR Code",
        },
        position: {
          left: 43,
          top: 188,
        },
        width: 150,
        height: 150,
        qrcode: qrcodeData,
      };

      const newElement = this.elementMachanism(data);

      this.addElement(newElement);
      this.selectedElement = newElement.id;
      this.updateProperties();
    },

    updateProperties(newProps?: Partial<ElementProperties>) {
      if (this.selectedElement === null) {
        this.currentProperties = {};
        return;
      }
      const boxes =
        this.activeSide === "front" ? this.frontBoxes : this.backBoxes;
      const element = boxes.find((e) => e.id === this.selectedElement);
      if (!element) return;

      if (newProps) {
        Object.assign(element.properties, newProps);
        if (newProps.size) {
          element.properties.size = { ...newProps.size };
        }
        if (newProps.qrcode) {
          element.properties.qrcode = { ...newProps.qrcode };
        }
        if (newProps.avatar) {
          element.properties.avatar = { ...newProps.avatar };
        }
        element.position.left = newProps.x ?? element.position.left;
        element.position.top = newProps.y ?? element.position.top;

        if (element.type === "avatar") {
          element.properties.avatar.containerStyle = this.computeContainerStyle(
            element.properties.avatar
          );
          element.properties.avatar.imageStyle = this.computeImageStyle();
        }
      } else {
        this.currentProperties = this.getElementProperties(element);
        if (element.type === "avatar") {
          this.avatarSize = Math.round(
            (element.properties.size.width + element.properties.size.height) / 2
          );
          this.avatarShape = element.properties.avatar.shape || "circle";
          this.avatarRadius = element.properties.avatar.radius || 32;
          this.avatarCustomClipPath =
            element.properties.avatar.customClipPath || "";
          this.avatarShowBorder = element.properties.avatar.showBorder;
          this.avatarShowRing = element.properties.avatar.showRing;
        }

        if (element.type == "qrcode") {
        }
      }
    },
    getElementProperties(element: CanvasElement): Partial<ElementProperties> {
      return {
        size: { ...element.properties.size },
        rotation: element.properties.rotation,
        font: element.properties.font,
        fontWeight: element.properties.fontWeight,
        fontStyle: element.properties.fontStyle,
        fontSize: element.properties.fontSize,
        fillColor: element.properties.fillColor,
        fillTransparency: element.properties.fillTransparency,
        imagePosition: element.properties.imagePosition,
        objectFit: element.properties.objectFit,
        textDecoration: element.properties.textDecoration,
        color: element.properties.color,
        textAlign: element.properties.textAlign,
        verticalAlign: element.properties.verticalAlign,
        horizontalAlign: element.properties.horizontalAlign,
        textTransform: element.properties.textTransform,
        src: element.properties.src,
        strokeColor: element.properties.strokeColor,
        strokeWidth: element.properties.strokeWidth,
        associatedData: element.properties.associatedData,
        content: element.properties.content,
        x: element.properties.x,
        y: element.properties.y,
        text: element.text,
        displayOption: element.properties.displayOption,
        qrcode: element.properties.qrcode,
        direction: element.properties.direction,
        avatar: element.properties.avatar
          ? { ...element.properties.avatar }
          : {
            showBorder: false,
            showRing: false,
            shape: "circle",
            radius: 32,
            customClipPath: "",
            containerStyle: [],
            avatar_src:
              "https://ui-avatars.com/api/?background=c8c9ca&color=6c757d&size=200",
            imageStyle: [],
          },
      };
    },

    updateElementText(id: string | number, newText: string) {
      const boxes =
        this.activeSide === "front" ? this.frontBoxes : this.backBoxes;
      const element = boxes.find((e) => e.id === id);
      if (element) {
        element.text = newText;
        element.properties.text = newText;
      }
    },

    alignHorizontal(alignment: "left" | "center" | "right") {
      if (this.selectedElement === null || !this.dropzone) return;
      const element = this.boxes.find((e) => e.id === this.selectedElement);
      if (!element) return;
      const canvasRect = this.dropzone.getBoundingClientRect();
      const elementWidth = element.properties.size.width;
      switch (alignment) {
        case "left":
          element.position.left = 0;
          element.properties.textAlign = "left";
          element.properties.horizontalAlign = "left";
          break;
        case "center":
          element.position.left = (canvasRect.width - elementWidth) / 2;
          element.properties.textAlign = "center";
          element.properties.horizontalAlign = "center";
          break;
        case "right":
          element.position.left = canvasRect.width - elementWidth;
          element.properties.textAlign = "right";
          element.properties.horizontalAlign = "right";
          break;
      }
      this.currentProperties.x = element.position.left;
      this.currentProperties.textAlign = alignment;
      this.currentProperties.horizontalAlign = alignment;
      this.updateProperties(this.currentProperties);
    },

    alignVertical(alignment: "top" | "middle" | "bottom") {
      if (this.selectedElement === null || !this.dropzone) return;
      const element = this.boxes.find((e) => e.id === this.selectedElement);
      if (!element) return;
      const canvasRect = this.dropzone.getBoundingClientRect();
      const elementHeight = element.properties.size.height;
      switch (alignment) {
        case "top":
          element.position.top = 0;
          element.properties.verticalAlign = "top";
          break;
        case "middle":
          element.position.top = (canvasRect.height - elementHeight) / 2;
          element.properties.verticalAlign = "middle";
          break;
        case "bottom":
          element.position.top = canvasRect.height - elementHeight;
          element.properties.verticalAlign = "bottom";
          break;
      }
      this.currentProperties.y = element.position.top;
      this.currentProperties.verticalAlign = alignment;
      this.updateProperties(this.currentProperties);
    },

    makeTransparent(color: string) {
      if (color === "fillColor") {
        this.currentProperties.fillTransparency = true;
        this.currentProperties.fillColor = "transparent";
        this.updateProperties(this.currentProperties);
      } else {
        this.currentProperties.color = "transparent";
        this.updateProperties(this.currentProperties);
      }
    },

    // makeQRCodeTransparent() {
    //   console.log("it works");

    //   this.currentProperties.qrcode.whiteColor = "transparent";
    //   this.updateProperties(this.currentProperties);
    // },

    applyTextAlign(align: string) {
      this.currentProperties.textAlign = align;
      this.currentProperties.horizontalAlign = align;
      const element = this.boxes.find((e) => e.id === this.selectedElement);
      if (element) {
        element.properties.textAlign = align;
        element.properties.horizontalAlign = align;
      }
      this.updateProperties(this.currentProperties);
    },

    applyVerticalAlign(align: string) {
      this.currentProperties.verticalAlign = align;
      const element = this.boxes.find((e) => e.id === this.selectedElement);
      if (element) {
        element.properties.verticalAlign = align;
      }
      this.updateProperties(this.currentProperties);
    },

    applyTextTransform(transform: string) {
      this.currentProperties.textTransform = transform;
      this.updateProperties(this.currentProperties);
    },

    toggleTextStyle(style: "bold" | "italic" | "underline") {
      if (style === "bold") {
        this.currentProperties.fontWeight =
          this.currentProperties.fontWeight === "bold" ? "normal" : "bold";
      } else if (style === "italic") {
        this.currentProperties.fontStyle =
          this.currentProperties.fontStyle === "italic" ? "normal" : "italic";
      } else if (style === "underline") {
        this.currentProperties.textDecoration =
          this.currentProperties.textDecoration === "underline"
            ? "none"
            : "underline";
      }
      this.updateProperties(this.currentProperties);
    },

    toggleLayerVisibility(id: string | number) {
      const boxes =
        this.activeSide === "front" ? this.frontBoxes : this.backBoxes;
      const el = boxes.find((e) => e.id === id);
      if (el) el.visible = !el.visible;
    },

    selectLayer(id: string | number) {
      this.selectedElement = id;
      this.updateProperties();
    },

    setTextDirection(id: string | number, direction: "ltr" | "rtl") {
      const element = this.boxes.find((e) => e.id === id);
      if (element) {
        element.properties.direction = direction;
        this.currentProperties.direction = direction;
        this.updateProperties(this.currentProperties);
      }
    },

    setBackground(background: string | null, side: "front" | "back") {
      if (side === "front") {
        this.frontBackground = background;
      } else {
        this.backBackground = background;
      }
    },

    applyGradient(gradient: string, side: "front" | "back") {
      this.setBackground(gradient, side);
      this.showGradientModal = false;
    },

    applyColor(color: string, side: "front" | "back") {
      this.setBackground(color, side);
      this.showColorModal = false;
    },

    setPunchArea(area: string, side: string) {
      console.log("Setting punch area:", area, "for side:", side);
      if (area === "none") {
        this.punchArea = false;
        this.punchCircle = "";
        this.punchLong = "";
      } else {
        this.punchArea = true;
        this.punchCircle = area;
        this.punchLong = area;
      }
    },
  },
  // persist: true,
});

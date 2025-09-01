// Root
fileReferenceOrigins ctxs:Vector<Origin> = FileReferenceOrigins;

origin flags:# predicate:string is_constructor:flags.0?true action:ActionOp needs_parent:flags.3?string parent_is_constructor:flags.4?true = origin;

// For string => TypedOp dictionaries
typedOpArg key:string value:TypedOp = TypedOpArg;

// Actions
callOp method:string args:Vector<TypedOpArg> stored_constructor:string = ActionOp;
getMessageOp flags:# peer:TypedOp id:TypedOp from_scheduled:flags.0?TypedOp stored_constructor:string = ActionOp;
noOp why:string = ActionOp;

// Field extraction path
paramNotFlag = ParamFlag;
paramIsFlagAbortIfEmpty = ParamFlag;
paramIsFlagFallback fallback:TypedOp = ParamFlag;
paramIsFlagPassthrough = ParamFlag;

pathPart flags:# constructor:string param:string flag:ParamFlag = PathPart;

path parts:Vector<PathPart> = Path;
pathParent parts:Vector<PathPart> = Path;

// Extractor+storer
extractAndStore from:Path to:string = FieldExtractor;

extractInputStickerSetFromDocumentAttributesAndStore from:Path to:string = FieldExtractor;
extractInputStickerSetFromStickerSetAndStore from:Path to:string = FieldExtractor;

extractPeerIdFromPeerAndStore from:Path to:string = FieldExtractor;
extractPeerIdFromInputPeerAndStore from:Path to:string = FieldExtractor;

extractChannelIdFromChannelAndStore from:Path to:string = FieldExtractor;
extractChannelIdFromInputChannelAndStore from:Path to:string = FieldExtractor;

extractUserIdFromUserAndStore from:Path to:string = FieldExtractor;
extractUserIdFromInputUserAndStore from:Path to:string = FieldExtractor;

// Typed constructors, the type is specified to simplify codegen,
// but isn't strictly necessary as it can be inferred from the TypedOpOp.
// It is fully pre-validated during the generation of the definition file.
typedOp type:string op:TypedOpOp = TypedOp;

// The actual ops
copyOp from:FieldExtractor = TypedOpOp;

getInputChannelByIdOp from:FieldExtractor = TypedOpOp;
getInputUserByIdOp from:FieldExtractor = TypedOpOp;
getInputPeerByIdOp from:FieldExtractor = TypedOpOp;

// Literals & constructors (methods not allowed or needed here)
constructorOp constructor:string args:Vector<TypedOpArg> = TypedOpOp;

vectorOp values:Vector<TypedOp> = TypedOpOp;

intLiteralOp value:int = TypedOpOp;
longLiteralOp value:long = TypedOpOp;
stringLiteralOp value:string = TypedOpOp;
boolLiteralOp value:Bool = TypedOpOp;
doubleLiteralOp value:double = TypedOpOp;
themeFormatLiteralOp = TypedOpOp;
